#!/usr/bin/env python3
"""
Golden Tor – marks every editable text on the public pages with a stable id.

    python3 scripts/tag-editable-text.py          # tag new texts, rewrite manifest
    python3 scripts/tag-editable-text.py --check  # CI: fail if anything is untagged/stale

Each text element gets data-gt-text="<page>.<n>" (header/footer texts share the
"header."/"footer." ids on every page). Elements that also contain inline markup
(<br>, <em>, links, icons) get data-gt-rich and are edited as limited HTML.
Existing ids are never renumbered, so saved texts stay attached after HTML edits.

Skipped on purpose (their text is written by JavaScript, so an override would be
overwritten): navigation, language switcher, anything with its own data-* hook,
status messages, aria-hidden decorations, form controls.

Only inserts attributes into opening tags (and one <script> tag per page); the rest
of the HTML is left byte-for-byte unchanged. Writes the manifest used by the
Filament "Teljes szövegek" page to filament-admin/resources/defaults/page-text.json.
"""
from __future__ import annotations

import glob
import html
import json
import os
import re
import sys
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MANIFEST = os.path.join(ROOT, "filament-admin", "resources", "defaults", "page-text.json")
SCRIPT_TAG = '<script src="site-text.js?v=2" defer></script>'
OLD_SCRIPT_TAG = re.compile(r'<script src="site-text\.js\?v=\d+" defer></script>')
EXCLUDED_PAGES = {"admin.html"}

VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"}
TEXT_TAGS = {"h1", "h2", "h3", "h4", "h5", "h6", "p", "li", "a", "button", "label", "small", "figcaption",
             "blockquote", "td", "th", "dt", "dd", "summary", "span", "strong", "em", "b", "i", "legend",
             "caption", "div", "address", "time"}
INLINE = {"a", "strong", "em", "b", "i", "br", "span", "small", "sup", "sub", "abbr", "time", "code", "mark", "u", "s"}
SKIP_TAGS = {"script", "style", "noscript", "svg", "select", "option", "textarea", "template", "head", "output",
             "iframe", "video", "audio", "canvas"}
ALLOWED_DATA = {"data-gt-text", "data-gt-rich", "data-cookie-settings", "data-calculator-tab"}
SKIP_CLASSES = {"language-switcher", "hp-field", "menu-toggle"}


class Node:
    def __init__(self, tag, attrs, start, end, parent):
        self.tag, self.attrs, self.start, self.end, self.parent = tag, dict(attrs), start, end, parent
        self.children: list = []  # Node | str

    def classes(self):
        return set((self.attrs.get("class") or "").split())


class TreeBuilder(HTMLParser):
    def __init__(self, source: str):
        super().__init__(convert_charrefs=False)
        self.source = source
        self.line_offsets = [0]
        for match in re.finditer("\n", source):
            self.line_offsets.append(match.end())
        self.root = Node("#root", [], 0, 0, None)
        self.stack = [self.root]

    def source_offset(self):
        line, col = self.getpos()
        return self.line_offsets[line - 1] + col

    def handle_starttag(self, tag, attrs):
        start = self.source_offset()
        text = self.get_starttag_text()
        node = Node(tag, attrs, start, start + len(text), self.stack[-1])
        self.stack[-1].children.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        start = self.source_offset()
        text = self.get_starttag_text()
        self.stack[-1].children.append(Node(tag, attrs, start, start + len(text), self.stack[-1]))

    def handle_endtag(self, tag):
        for index in range(len(self.stack) - 1, 0, -1):
            if self.stack[index].tag == tag:
                del self.stack[index:]
                return

    def handle_data(self, data):
        self.stack[-1].children.append(data)

    def handle_entityref(self, name):
        self.stack[-1].children.append(html.unescape(f"&{name};"))

    def handle_charref(self, name):
        self.stack[-1].children.append(html.unescape(f"&#{name};"))


def has_js_hook(node: Node) -> bool:
    return any(name.startswith("data-") and not name.startswith("data-gt-") and name not in ALLOWED_DATA for name in node.attrs)


def skipped(node: Node) -> bool:
    return (node.tag in SKIP_TAGS or node.attrs.get("aria-hidden") == "true"
            or node.attrs.get("role") == "status" or "data-gt-skip" in node.attrs
            or bool(node.classes() & SKIP_CLASSES) or "data-nav" in node.attrs)


def inline_only(node: Node) -> bool:
    for child in node.children:
        if isinstance(child, Node):
            if child.tag not in INLINE or has_js_hook(child) or not inline_only(child):
                return False
    return True


def text_of(node: Node) -> str:
    return "".join(c if isinstance(c, str) else text_of(c) for c in node.children)


def direct_text(node: Node) -> str:
    return "".join(c for c in node.children if isinstance(c, str))


def inner_html(source: str, node: Node) -> str:
    """Inner HTML rebuilt from the parsed tree (without our own data-gt-* attributes)."""
    return "".join(html.escape(c, quote=False) if isinstance(c, str) else outer_html(source, c) for c in node.children)


def outer_html(source: str, node: Node) -> str:
    opening = re.sub(r'\sdata-gt-[a-z]+(?:="[^"]*")?', "", source[node.start:node.end])
    if node.tag in VOID:
        return opening
    return opening + inner_html(source, node) + f"</{node.tag}>"


def candidate(node: Node) -> bool:
    if node.tag not in TEXT_TAGS or has_js_hook(node) or skipped(node):
        return False
    return inline_only(node) and bool(text_of(node).strip())


def collect(node: Node, found: list, area: str | None = None):
    for child in node.children:
        if not isinstance(child, Node):
            continue
        if skipped(child):
            continue
        child_area = area
        if child.tag == "footer":
            child_area = "footer"
        elif child.tag == "header" and "site-header" in child.classes():
            child_area = "header"
        if "data-gt-text" in child.attrs:
            found.append((child, child_area))
            continue
        if candidate(child):
            elements = [c for c in child.children if isinstance(c, Node)]
            # <li><a>Text</a></li>: tag the single inner element instead of its wrapper.
            if not direct_text(child).strip() and len(elements) == 1 and candidate(elements[0]):
                found.append((elements[0], child_area))
            else:
                found.append((child, child_area))
            continue
        collect(child, found, child_area)


STRUCTURE_REPORT: dict[str, int] = {}
ITEM_TAGS = {"li", "p", "div", "a", "article", "figure", "blockquote", "section", "aside", "dd", "dt", "tr"}
STRUCTURE = [
    # attribute, id letter
    ("data-gt-block", "s"),
    ("data-gt-list", "l"),
    ("data-gt-item", "i"),
    ("data-gt-img", "m"),
    ("data-gt-link", "k"),
]


def area_of(node: Node) -> str | None:
    while node is not None:
        if node.tag == "footer":
            return "footer"
        if node.tag == "header" and "site-header" in node.classes():
            return "header"
        node = node.parent
    return None


def inside(node: Node, tags: set) -> bool:
    node = node.parent
    while node is not None:
        if node.tag in tags:
            return True
        node = node.parent
    return False


def structure_nodes(root: Node) -> dict[str, list]:
    """Sections of <main>, repeated groups (lists + items), images and links."""
    found: dict[str, list] = {attribute: [] for attribute, _ in STRUCTURE}

    def signature(node: Node):
        return node.tag, tuple(sorted(node.classes()))

    def walk(node: Node):
        elements = [c for c in node.children if isinstance(c, Node) and not skipped(c)]
        if node.tag == "main":
            for section in elements:
                if section.tag not in ("script", "style", "template"):
                    found["data-gt-block"].append(section)
        elif node.tag not in ("form", "select", "nav") or (node.tag == "nav" and "data-nav" not in node.attrs):
            groups: dict = {}
            for child in elements:
                if child.tag in ITEM_TAGS and not has_js_hook(child) and not inside(child, {"form"}):
                    groups.setdefault(signature(child), []).append(child)
            items = [child for members in groups.values() if len(members) >= 2 for child in members]
            if items and not has_js_hook(node):
                found["data-gt-list"].append(node)
                found["data-gt-item"].extend(sorted(items, key=lambda n: n.start))
        for child in elements:
            if child.tag == "img":
                found["data-gt-img"].append(child)
            elif child.tag == "a" and "href" in child.attrs and not has_js_hook(child):
                found["data-gt-link"].append(child)
            walk(child)

    walk(root)
    return found


def assign_structure(source: str, page: str, root: Node, check: bool, path: str, inserts: list, problems: list, report: dict):
    found = structure_nodes(root)
    for attribute, letter in STRUCTURE:
        nodes = found[attribute]
        taken = {n.attrs[attribute] for n in nodes if n.attrs.get(attribute)}
        highest: dict[str, int] = {}
        for key in taken:
            prefix, _, rest = key.partition(".")
            if rest[1:].isdigit():
                highest[prefix] = max(highest.get(prefix, 0), int(rest[1:]))
        position: dict[str, int] = {}
        for node in nodes:
            area = area_of(node)
            prefix = area or page
            position[prefix] = position.get(prefix, 0) + 1
            report[letter] = report.get(letter, 0) + (0 if area and page != "index" else 1)
            if node.attrs.get(attribute):
                continue
            if check:
                problems.append(f"{path}: missing {attribute} on <{node.tag}> {text_of(node).strip()[:40]!r}")
                continue
            key = f"{prefix}.{letter}{position[prefix]}" if area else ""
            if not key or key in taken:
                highest[prefix] = highest.get(prefix, 0) + 1
                key = f"{prefix}.{letter}{highest[prefix]}"
            taken.add(key)
            highest[prefix] = max(highest.get(prefix, 0), int(key.split(".")[1][1:]))
            opening = source[node.start:node.end]
            inserts.append((node.end - (2 if opening.endswith("/>") else 1), f' {attribute}="{key}"'))


def process(path: str, manifest: dict, check: bool) -> list[str]:
    page = os.path.splitext(os.path.basename(path))[0]
    with open(path, encoding="utf-8") as handle:
        source = handle.read()
    builder = TreeBuilder(source)
    builder.feed(source)
    found: list = []
    collect(builder.root, found)

    problems = []
    inserts = []  # (offset, text)
    changed_script = False
    taken = {node.attrs["data-gt-text"] for node, _ in found if "data-gt-text" in node.attrs}
    highest: dict[str, int] = {}
    for key in taken:
        prefix, _, number = key.partition(".")
        if number.isdigit():
            highest[prefix] = max(highest.get(prefix, 0), int(number))
    position_in_area: dict[str, int] = {}

    for node, area in found:
        prefix = area or page
        position_in_area[prefix] = position_in_area.get(prefix, 0) + 1
        rich = any(isinstance(c, Node) for c in node.children)
        key = node.attrs.get("data-gt-text")
        opening = source[node.start:node.end]
        tag_end = node.end - (2 if opening.endswith("/>") else 1)
        if not key:
            if check:
                problems.append(f"{path}: untagged text <{node.tag}> {text_of(node).strip()[:60]!r}")
                continue
            # Shared header/footer texts are numbered by position so every page gets the same ids.
            key = f"{prefix}.{position_in_area[prefix]}" if area else ""
            if not key or key in taken:
                highest[prefix] = highest.get(prefix, 0) + 1
                key = f"{prefix}.{highest[prefix]}"
            taken.add(key)
            number = int(key.split(".")[1])
            highest[prefix] = max(highest.get(prefix, 0), number)
            inserts.append((tag_end, f' data-gt-text="{key}"' + (" data-gt-rich" if rich else "")))
        elif rich and "data-gt-rich" not in node.attrs:
            if check:
                problems.append(f"{path}: {key} needs data-gt-rich")
                continue
            inserts.append((tag_end, " data-gt-rich"))

        original = inner_html(source, node).strip() if rich else re.sub(r"\s+", " ", text_of(node)).strip()
        entry = manifest.get(key)
        if entry is None:
            manifest[key] = {"page": prefix, "pages": [page], "tag": node.tag, "rich": rich, "text": original}
        else:
            if entry["text"] != original:
                problems.append(f"{path}: shared id {key} differs from {entry['pages'][0]}: {original[:60]!r}")
            if page not in entry["pages"]:
                entry["pages"].append(page)

    assign_structure(source, page, builder.root, check, path, inserts, problems, STRUCTURE_REPORT)

    if SCRIPT_TAG not in source and OLD_SCRIPT_TAG.search(source):
        # Bump the cache-busting version of an existing tag instead of adding a second one.
        if check:
            problems.append(f"{path}: outdated site-text.js version, expected {SCRIPT_TAG}")
        else:
            match = OLD_SCRIPT_TAG.search(source)
            source = source[:match.start()] + SCRIPT_TAG + source[match.end():]
            inserts = [(pos + (len(SCRIPT_TAG) - (match.end() - match.start())) if pos > match.start() else pos, text) for pos, text in inserts]
            changed_script = True
    if SCRIPT_TAG not in source:
        if check:
            problems.append(f"{path}: missing {SCRIPT_TAG}")
        else:
            position = source.rfind("</body>")
            if position < 0:
                problems.append(f"{path}: no </body>")
            else:
                inserts.append((position, SCRIPT_TAG))

    if (inserts or changed_script) and not check:
        for position, text in sorted(inserts, reverse=True):
            source = source[:position] + text + source[position:]
        with open(path, "w", encoding="utf-8") as handle:
            handle.write(source)
    return problems


def main() -> int:
    check = "--check" in sys.argv
    pages = sorted(p for p in glob.glob(os.path.join(ROOT, "*.html")) if os.path.basename(p) not in EXCLUDED_PAGES)
    manifest: dict = {}
    problems: list[str] = []
    for path in pages:
        problems += process(path, manifest, check)

    ordered = {key: manifest[key] for key in sorted(manifest, key=lambda k: (k.split(".")[0], int(k.split(".")[1])))}
    output = json.dumps(ordered, ensure_ascii=False, indent=1) + "\n"
    if check:
        try:
            with open(MANIFEST, encoding="utf-8") as handle:
                if handle.read() != output:
                    problems.append(f"{MANIFEST} is stale; run scripts/tag-editable-text.py")
        except FileNotFoundError:
            problems.append(f"{MANIFEST} missing; run scripts/tag-editable-text.py")
    else:
        os.makedirs(os.path.dirname(MANIFEST), exist_ok=True)
        with open(MANIFEST, "w", encoding="utf-8") as handle:
            handle.write(output)

    for problem in problems:
        print("HIBA:", problem, file=sys.stderr)
    if not problems:
        by_page: dict[str, int] = {}
        for entry in manifest.values():
            by_page[entry["page"]] = by_page.get(entry["page"], 0) + 1
        print(f"{len(manifest)} szerkeszthető szöveg:", ", ".join(f"{p} {n}" for p, n in sorted(by_page.items())))
        names = {"s": "szakasz", "l": "elemcsoport", "i": "mozgatható elem", "m": "kép", "k": "link"}
        print("Szerkezet:", ", ".join(f"{STRUCTURE_REPORT.get(k, 0)} {v}" for k, v in names.items()))
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
