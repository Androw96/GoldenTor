/*
 * Golden Tor – on-page content editor.
 *
 * Visitors: applies the saved page structure (/api/content/page_layout) and texts
 * (/api/content/page_text) to the marked elements. Anything without a saved change keeps
 * what the HTML contains, so nothing disappears when the API is unavailable.
 *
 * Logged-in admins (Filament session): "✎ Oldal szerkesztése" button with
 *  - texts:      click and type (data-gt-text; data-gt-rich keeps <br>, <em>, links, icons)
 *  - items:      move up/down, duplicate, hide; delete own copies (data-gt-item in data-gt-list)
 *  - sections:   move up/down, hide (data-gt-block, direct children of <main>)
 *  - images:     upload a replacement (data-gt-img)
 *  - links:      change the target address (data-gt-link)
 * Saved through /admin/inline-editor/* (CSRF protected, validated and sanitised again
 * on the server). Ids come from scripts/tag-editable-text.py.
 */
(() => {
  const TEXT = "data-gt-text";
  const STRUCT_ATTRS = ["data-gt-block", "data-gt-list", "data-gt-item", "data-gt-img", "data-gt-link"];
  const ALL_ID_ATTRS = [TEXT, ...STRUCT_ATTRS];
  const API_TEXT = "/api/content/page_text?lang=hu";
  const API_LAYOUT = "/api/content/page_layout?lang=hu";
  const SESSION = "/admin/inline-editor/session";
  const SAVE_TEXT = "/admin/inline-editor/texts";
  const SAVE_LAYOUT = "/admin/inline-editor/layout";
  const UPLOAD = "/admin/inline-editor/upload";
  const ALLOWED = {
    A: ["href", "target", "rel", "class"], STRONG: ["class"], EM: ["class"], B: [], I: [], BR: [],
    SPAN: ["class", "aria-hidden"], SMALL: ["class"], SUP: [], SUB: [],
  };
  const SAFE_URL = /^(https?:\/\/|mailto:|tel:|\/(?!\/)|#|[\w.\-]+\.html(?:[?#].*)?$|assets\/)/i;

  const language = (() => { try { return localStorage.getItem("goldenTorLanguage") || "hu"; } catch { return "hu"; } })();
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const byAttr = (attribute, id) => document.querySelector(`[${attribute}="${CSS.escape(id)}"]`);
  const prefixOf = (id) => String(id).split(".")[0];
  const baseKey = (id) => String(id).split("@")[0];
  const isRich = (el) => el.hasAttribute("data-gt-rich");
  const page = (document.querySelector("[data-gt-block]")?.getAttribute("data-gt-block") || "").split(".")[0]
    || (location.pathname.split("/").pop() || "index.html").replace(/\.html$/, "") || "index";

  // Hidden elements stay in the DOM (so the editor can bring them back).
  const baseStyle = document.createElement("style");
  baseStyle.textContent = "body:not(.gt-ed-on) [data-gt-hidden]{display:none!important}";
  document.head.append(baseStyle);

  // Original HTML of every text, by id, before any saved change is applied.
  const originalsByKey = new Map();
  $$(`[${TEXT}]`).forEach((el) => originalsByKey.set(el.getAttribute(TEXT), el.innerHTML));
  const originalHtml = (el) => originalsByKey.get(el.getAttribute(TEXT)) ?? originalsByKey.get(baseKey(el.getAttribute(TEXT))) ?? "";

  let savedTexts = {};
  let layouts = {}; // prefix -> {order, hidden, clones, images, links}

  function sanitize(html) {
    const template = document.createElement("template");
    template.innerHTML = html;
    const clean = (parent) => {
      [...parent.childNodes].forEach((node) => {
        if (node.nodeType === Node.COMMENT_NODE) { node.remove(); return; }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "TEMPLATE"].includes(node.tagName)) { node.remove(); return; }
        clean(node);
        const allowed = ALLOWED[node.tagName];
        if (!allowed) {
          if (["DIV", "P"].includes(node.tagName) && node.previousSibling) node.before(document.createElement("br"));
          node.replaceWith(...node.childNodes);
          return;
        }
        [...node.attributes].forEach((attribute) => { if (!allowed.includes(attribute.name)) node.removeAttribute(attribute.name); });
        if (node.tagName === "A" && node.hasAttribute("href") && !SAFE_URL.test(node.getAttribute("href").trim())) node.removeAttribute("href");
      });
    };
    clean(template.content);
    return template.innerHTML.trim();
  }

  // ------------------------------------------------------------- applying
  function applyTexts() {
    if (language !== "hu") return;
    $$(`[${TEXT}]`).forEach((el) => {
      const entry = savedTexts[el.getAttribute(TEXT)];
      if (!entry || typeof entry.v !== "string") return;
      if (isRich(el)) el.innerHTML = sanitize(entry.v); else el.textContent = entry.v;
      el.setAttribute("data-gt-overridden", "");
    });
  }

  function emptyLayout() { return { order: {}, hidden: [], clones: {}, images: {}, links: {} }; }
  function layoutFor(prefix) {
    layouts[prefix] = Object.assign(emptyLayout(), layouts[prefix] || {});
    return layouts[prefix];
  }

  /** Deep copy of an item with fresh ids: texts and nested ids get an "@cN" suffix. */
  function cloneItem(source, cloneId) {
    const suffix = "@" + cloneId.split(".")[1];
    const copy = source.cloneNode(true);
    [copy, ...$$("*", copy)].forEach((el) => {
      ALL_ID_ATTRS.forEach((attribute) => {
        if (el.hasAttribute(attribute)) el.setAttribute(attribute, baseKey(el.getAttribute(attribute)) + suffix);
      });
      ["id", "data-gt-dirty", "data-gt-hidden", "contenteditable", "data-gt-overridden"].forEach((a) => el.removeAttribute(a));
    });
    copy.setAttribute("data-gt-item", cloneId);
    copy.setAttribute("data-gt-clone", "");
    return copy;
  }

  function containerOf(id) {
    return id.endsWith(".main") ? document.querySelector("main") : byAttr("data-gt-list", id);
  }

  function childIdsOf(container) {
    if (container === document.querySelector("main")) {
      return [...container.children].filter((c) => c.hasAttribute("data-gt-block")).map((c) => c.getAttribute("data-gt-block"));
    }
    return [...container.children].filter((c) => c.hasAttribute("data-gt-item")).map((c) => c.getAttribute("data-gt-item"));
  }

  /** Reorders the movable children of a container; other children keep their place. */
  function reorder(container, ids) {
    const attribute = container === document.querySelector("main") ? "data-gt-block" : "data-gt-item";
    const movable = [...container.children].filter((c) => c.hasAttribute(attribute));
    const wanted = ids.map((id) => movable.find((c) => c.getAttribute(attribute) === id)).filter(Boolean);
    const rest = movable.filter((c) => !wanted.includes(c));
    const sequence = [...wanted, ...rest];
    const slots = movable.map((c) => { const marker = document.createComment("slot"); c.before(marker); return marker; });
    slots.forEach((marker, index) => marker.replaceWith(sequence[index]));
  }

  function applyLayouts() {
    if (language !== "hu") return;
    Object.entries(layouts).forEach(([, layout]) => {
      Object.entries(layout.images || {}).forEach(([id, image]) => {
        const img = byAttr("data-gt-img", id);
        if (img && image && SAFE_URL.test(image.src || "")) { img.src = image.src; img.removeAttribute("srcset"); if (typeof image.alt === "string") img.alt = image.alt; }
      });
      Object.entries(layout.links || {}).forEach(([id, link]) => {
        const a = byAttr("data-gt-link", id);
        if (a && link && SAFE_URL.test(link.href || "")) a.setAttribute("href", link.href);
      });
    });
    // Copies: created from the source as it currently looks (texts already applied).
    Object.values(layouts).forEach((layout) => {
      Object.entries(layout.clones || {}).forEach(([cloneId, clone]) => {
        if (!clone || byAttr("data-gt-item", cloneId)) return;
        const source = byAttr("data-gt-item", clone.source);
        const list = clone.list ? byAttr("data-gt-list", clone.list) : source?.parentElement;
        if (source && list) list.append(cloneItem(source, cloneId));
      });
    });
    Object.values(layouts).forEach((layout) => {
      Object.entries(layout.order || {}).forEach(([containerId, ids]) => {
        const container = containerOf(containerId);
        if (container && Array.isArray(ids)) reorder(container, ids);
      });
      (layout.hidden || []).forEach((id) => {
        const el = byAttr("data-gt-item", id) || byAttr("data-gt-block", id) || byAttr(TEXT, id) || byAttr("data-gt-link", id);
        if (el) el.setAttribute("data-gt-hidden", "");
      });
    });
  }

  const getJson = (url) => fetch(url, { credentials: "same-origin" }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
  const ready = Promise.all([getJson(API_TEXT), getJson(API_LAYOUT)]).then(([text, layout]) => {
    savedTexts = (text && text.payload && typeof text.payload === "object") ? text.payload : {};
    layouts = (layout && layout.payload && typeof layout.payload === "object" && !Array.isArray(layout.payload)) ? layout.payload : {};
    applyTexts();
    applyLayouts();
    applyTexts(); // copies get their own saved texts
    applyLayouts.done = true;
  }).catch(() => {});

  // ---------------------------------------------------------------- editor
  const hasEditorHint = () => /(?:^|;\s*)gt_editor=1(?:;|$)/.test(document.cookie);
  if (!hasEditorHint() && !new URLSearchParams(location.search).has("gt-edit")) return;

  ready.then(() => fetch(SESSION, { credentials: "same-origin", headers: { Accept: "application/json" } }))
    .then((response) => (response && response.ok ? response.json() : null))
    .then((session) => {
      if (!session || !session.editor) { document.cookie = "gt_editor=; Max-Age=0; path=/; SameSite=Lax"; return; }
      mountEditor(session.csrf);
    })
    .catch(() => {});

  function mountEditor(csrf) {
    const style = document.createElement("style");
    style.textContent = `
      .gt-ed-bar,.gt-ed-tools{font:600 13px/1.3 system-ui,-apple-system,"Segoe UI",sans-serif;color:#f8f3ee}
      .gt-ed-bar{position:fixed;right:16px;bottom:16px;z-index:2147483000;display:flex;flex-wrap:wrap;gap:8px;align-items:center;
        max-width:calc(100vw - 32px);padding:10px 12px;border-radius:12px;background:#141c17;box-shadow:0 12px 40px rgba(0,0,0,.45);border:1px solid #2f4136}
      .gt-ed-bar button,.gt-ed-tools button{all:unset;cursor:pointer;padding:8px 12px;border-radius:8px;background:#24382d;color:#f8f3ee;font:inherit;white-space:nowrap}
      .gt-ed-bar button:hover,.gt-ed-tools button:hover{background:#2f4a3b}
      .gt-ed-bar button[disabled]{opacity:.45;cursor:default}
      .gt-ed-bar .gt-ed-primary{background:#b8962e;color:#141c17}
      .gt-ed-bar .gt-ed-primary:hover{background:#cfac45}
      .gt-ed-bar .gt-ed-status{font-weight:500;color:#cdd8d0;padding:0 4px}
      .gt-ed-tools{position:absolute;z-index:2147482999;display:none;gap:4px;padding:4px;border-radius:9px;background:#141c17;border:1px solid #2f4136;box-shadow:0 6px 20px rgba(0,0,0,.4)}
      .gt-ed-tools button{padding:5px 8px;font-size:12px}
      .gt-ed-tools .gt-ed-label{align-self:center;padding:0 6px;color:#ebf150;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
      .gt-ed-tools .gt-ed-danger{background:#5b2626}
      body.gt-ed-on [${TEXT}]{outline:1px dashed rgba(235,241,80,.55);outline-offset:3px;cursor:text}
      body.gt-ed-on [${TEXT}]:hover{outline:2px solid #ebf150}
      body.gt-ed-on [${TEXT}]:focus{outline:2px solid #ebf150;box-shadow:0 0 0 6px rgba(235,241,80,.18)}
      body.gt-ed-on [data-gt-dirty]{outline:2px solid #4ade80!important}
      body.gt-ed-on .gt-ed-hover{box-shadow:0 0 0 2px rgba(102,180,255,.85)!important}
      body.gt-ed-on [data-gt-hidden]{opacity:.35;outline:2px dashed #f87171!important;outline-offset:4px}
      body.gt-ed-on [data-gt-clone]{box-shadow:inset 0 0 0 1px rgba(74,222,128,.4)}
    `;
    document.head.append(style);

    const bar = document.createElement("div");
    bar.className = "gt-ed-bar";
    bar.setAttribute("role", "toolbar");
    bar.setAttribute("aria-label", "Oldalszerkesztő");
    bar.innerHTML = `
      <button type="button" data-act="toggle" class="gt-ed-primary">✎ Oldal szerkesztése</button>
      <span class="gt-ed-status" data-status hidden></span>
      <button type="button" data-act="save" class="gt-ed-primary" hidden disabled>Mentés</button>
      <button type="button" data-act="discard" hidden disabled>Elvetés</button>
      <button type="button" data-act="reset" hidden disabled title="A kijelölt szöveg visszaállítása az eredetire">Szöveg visszaállítása</button>
      <a href="/admin/teljes-szovegek" style="color:#d6b85a;padding:0 4px" data-admin-link hidden>Lista az adminban</a>`;
    document.body.append(bar);

    const tools = document.createElement("div");
    tools.className = "gt-ed-tools";
    document.body.append(tools);
    const sectionTools = document.createElement("div");
    sectionTools.className = "gt-ed-tools";
    document.body.append(sectionTools);

    const fileInput = Object.assign(document.createElement("input"), { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });
    document.body.append(fileInput);

    const $ = (selector) => bar.querySelector(selector);
    const status = $("[data-status]");
    const textChanges = new Map(); // element -> {reset?: true}
    const dirtyLayouts = new Set();
    let editing = false;
    let focused = null;
    let leaving = false;

    const setStatus = (text) => { status.hidden = !text; status.textContent = text || ""; };
    const changeCount = () => textChanges.size + dirtyLayouts.size;
    const refresh = () => {
      $('[data-act="save"]').disabled = changeCount() === 0;
      $('[data-act="discard"]').disabled = changeCount() === 0;
      $('[data-act="reset"]').disabled = !focused || !(focused.hasAttribute("data-gt-overridden") || textChanges.has(focused));
      if (editing) {
        const parts = [];
        if (textChanges.size) parts.push(`${textChanges.size} szöveg`);
        if (dirtyLayouts.size) parts.push("szerkezet");
        setStatus(parts.length ? `Mentetlen: ${parts.join(" + ")}` : "Kattints egy szövegre, vagy vidd az egeret egy elemre");
      }
    };

    function makeEditable(root = document) {
      $$(`[${TEXT}]`, root).forEach((el) => {
        if (!editing) { el.removeAttribute("contenteditable"); return; }
        if (isRich(el)) { el.contentEditable = "true"; return; }
        el.contentEditable = "plaintext-only";
        if (el.contentEditable !== "plaintext-only") el.contentEditable = "true";
      });
      if (root !== document && root.hasAttribute && root.hasAttribute(TEXT)) makeEditable(root.parentElement);
    }

    function setEditing(on) {
      editing = on;
      document.body.classList.toggle("gt-ed-on", on);
      $('[data-act="toggle"]').textContent = on ? "Kilépés a szerkesztésből" : "✎ Oldal szerkesztése";
      $('[data-act="toggle"]').classList.toggle("gt-ed-primary", !on);
      ["save", "discard", "reset"].forEach((act) => { $(`[data-act="${act}"]`).hidden = !on; });
      $("[data-admin-link]").hidden = !on;
      makeEditable();
      if (!on) { setStatus(""); hideTools(); }
      refresh();
    }

    function markText(el) { textChanges.set(el, textChanges.get(el)?.reset ? {} : (textChanges.get(el) || {})); el.setAttribute("data-gt-dirty", ""); refresh(); }
    function markLayout(prefix) { dirtyLayouts.add(prefix); refresh(); }

    // ---------- structure operations
    function recordOrder(container) {
      const id = container === document.querySelector("main") ? `${page}.main` : container.getAttribute("data-gt-list");
      const layout = layoutFor(prefixOf(id));
      layout.order[id] = childIdsOf(container);
      markLayout(prefixOf(id));
    }

    function move(el, direction) {
      const attribute = el.hasAttribute("data-gt-item") ? "data-gt-item" : "data-gt-block";
      const siblings = [...el.parentElement.children].filter((c) => c.hasAttribute(attribute));
      const index = siblings.indexOf(el);
      const target = siblings[index + direction];
      if (!target) return;
      if (direction < 0) target.before(el); else target.after(el);
      recordOrder(el.parentElement);
      el.scrollIntoView({ block: "nearest", behavior: "smooth" });
      showToolsFor(el);
    }

    function nextCloneId(prefix) {
      const used = new Set([...Object.keys(layoutFor(prefix).clones), ...$$("[data-gt-item]").map((e) => e.getAttribute("data-gt-item"))]);
      let n = 1;
      while (used.has(`${prefix}.c${n}`)) n++;
      return `${prefix}.c${n}`;
    }

    function duplicate(item) {
      const list = item.parentElement;
      const listId = list.getAttribute("data-gt-list");
      if (!listId) return;
      const prefix = prefixOf(listId);
      const cloneId = nextCloneId(prefix);
      const copy = cloneItem(item, cloneId);
      $$(`[${TEXT}]`, copy).concat(copy.hasAttribute(TEXT) ? [copy] : []).forEach((el) => {
        // A copy starts with the source's current text; save it so visitors see the same.
        textChanges.set(el, {});
        el.setAttribute("data-gt-dirty", "");
      });
      item.after(copy);
      layoutFor(prefix).clones[cloneId] = { source: item.getAttribute("data-gt-item"), list: listId };
      recordOrder(list);
      makeEditable(copy);
      copy.scrollIntoView({ block: "nearest", behavior: "smooth" });
      setStatus("Másolat kész – írd át a szövegét, majd ments.");
      showToolsFor(copy);
    }

    function toggleHidden(el, id) {
      const layout = layoutFor(prefixOf(id));
      const hidden = new Set(layout.hidden);
      if (el.hasAttribute("data-gt-hidden")) { el.removeAttribute("data-gt-hidden"); hidden.delete(id); }
      else { el.setAttribute("data-gt-hidden", ""); hidden.add(id); }
      layout.hidden = [...hidden];
      markLayout(prefixOf(id));
      showToolsFor(el);
    }

    function removeClone(item) {
      const id = item.getAttribute("data-gt-item");
      if (!confirm("Biztosan törlöd ezt a másolatot?")) return;
      const list = item.parentElement;
      const layout = layoutFor(prefixOf(id));
      delete layout.clones[id];
      layout.hidden = layout.hidden.filter((h) => h !== id);
      $$(`[${TEXT}]`, item).concat(item.hasAttribute(TEXT) ? [item] : []).forEach((el) => {
        if (el.hasAttribute("data-gt-overridden")) textChanges.set(el, { reset: true }); else textChanges.delete(el);
      });
      item.remove();
      recordOrder(list);
      hideTools();
    }

    async function replaceImage(img) {
      fileInput.value = "";
      fileInput.onchange = async () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) { setStatus("A kép legfeljebb 8 MB lehet."); return; }
        const form = new FormData();
        form.append("image", file);
        setStatus("Kép feltöltése…");
        try {
          const response = await fetch(UPLOAD, { method: "POST", credentials: "same-origin", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: form });
          const data = await response.json().catch(() => ({}));
          if (!response.ok) throw new Error(data.message || `Feltöltés sikertelen (hibakód: ${response.status}).`);
          const id = img.getAttribute("data-gt-img");
          img.src = data.url;
          img.removeAttribute("srcset");
          const alt = prompt("Rövid leírás a képhez (akadálymentességhez, elhagyható):", img.alt || "");
          if (alt !== null) img.alt = alt;
          layoutFor(prefixOf(id)).images[id] = { src: data.url, alt: img.alt };
          markLayout(prefixOf(id));
          setStatus("Kép feltöltve – ne felejtsd el menteni.");
        } catch (error) { setStatus(error.message || "Kapcsolódási hiba."); }
      };
      fileInput.click();
    }

    function editLink(a) {
      const id = a.getAttribute("data-gt-link");
      const current = a.getAttribute("href") || "";
      const value = prompt("Hová mutasson a link?\nPélda: idopont.html, https://…, mailto:…, tel:+36…", current);
      if (value === null) return;
      const href = value.trim();
      if (!SAFE_URL.test(href)) { alert("Ez a cím nem használható. Adj meg teljes https:// címet, egy .html oldalt, mailto: vagy tel: linket."); return; }
      a.setAttribute("href", href);
      layoutFor(prefixOf(id)).links[id] = { href };
      markLayout(prefixOf(id));
    }

    // ---------- hover tools
    let current = null;
    let currentSection = null;
    let hideTimer = null;
    let toolsTimer = null;
    let retargetTimer = null;

    function button(label, action, extraClass = "") {
      const b = document.createElement("button");
      b.type = "button"; b.textContent = label; if (extraClass) b.className = extraClass;
      b.addEventListener("click", (event) => { event.preventDefault(); event.stopPropagation(); action(); });
      return b;
    }

    function place(panel, el) {
      const rect = el.getBoundingClientRect();
      panel.style.display = "flex";
      const width = panel.offsetWidth;
      panel.style.top = `${Math.max(window.scrollY + 4, window.scrollY + rect.top - panel.offsetHeight - 6)}px`;
      panel.style.left = `${Math.max(4, Math.min(window.scrollX + rect.right - width, window.scrollX + document.documentElement.clientWidth - width - 4))}px`;
    }

    function baseFor(target) {
      const item = target.closest("[data-gt-item]");
      return item || target.closest(`[${TEXT}]`) || target.closest("[data-gt-link]") || target.closest("img[data-gt-img]");
    }

    function showToolsFor(target) {
      if (!editing || !target || !document.contains(target)) { hideTools(); return; }
      clearTimeout(retargetTimer);
      const item = target.closest("[data-gt-item]");
      const link = target.closest("[data-gt-link]") || (item && item.matches("[data-gt-link]") ? item : null) || (item ? item.querySelector("[data-gt-link]") : null);
      const img = target.closest("img[data-gt-img]") || (item ? item.querySelector("img[data-gt-img]") : null);
      const text = target.closest(`[${TEXT}]`);
      const base = item || text || link || img;
      if (base) {
        clearTimeout(toolsTimer);
        document.querySelectorAll(".gt-ed-hover").forEach((e) => e.classList.remove("gt-ed-hover"));
        tools.replaceChildren();
        current = base;
        base.classList.add("gt-ed-hover");
        if (item) {
          tools.append(Object.assign(document.createElement("span"), { className: "gt-ed-label", textContent: item.hasAttribute("data-gt-clone") ? "Másolat" : "Elem" }));
          tools.append(button("↑", () => move(item, -1)), button("↓", () => move(item, 1)), button("⧉ Másolat", () => duplicate(item)));
        }
        if (img) tools.append(button("🖼 Kép", () => replaceImage(img)));
        if (link) tools.append(button("🔗 Link", () => editLink(link)));
        if (item && item.hasAttribute("data-gt-clone")) tools.append(button("🗑 Törlés", () => removeClone(item), "gt-ed-danger"));
        else {
          const hideTarget = item || text || link;
          const hideId = item ? item.getAttribute("data-gt-item") : text ? text.getAttribute(TEXT) : link?.getAttribute("data-gt-link");
          if (hideTarget && hideId) tools.append(button(hideTarget.hasAttribute("data-gt-hidden") ? "👁 Megjelenítés" : "🚫 Elrejtés", () => toggleHidden(hideTarget, hideId)));
        }
        place(tools, base);
      }

      showSectionTools(target);
    }

    // Keep the section panel below the fixed site header.
    function headerOffset() {
      const header = document.querySelector(".site-header");
      const rect = header ? header.getBoundingClientRect() : null;
      return rect && getComputedStyle(header).position !== "static" ? Math.max(8, rect.bottom) : 8;
    }

    function showSectionTools(target) {
      if (!editing) return;
      const section = target.closest("[data-gt-block]");
      sectionTools.replaceChildren();
      if (section) {
        currentSection = section;
        const id = section.getAttribute("data-gt-block");
        const ownImg = $$("img[data-gt-img]", section).find((i) => !i.closest("[data-gt-item]"));
        sectionTools.append(Object.assign(document.createElement("span"), { className: "gt-ed-label", textContent: "Szakasz" }));
        sectionTools.append(button("↑", () => move(section, -1)), button("↓", () => move(section, 1)));
        if (ownImg) sectionTools.append(button("🖼 Háttérkép", () => replaceImage(ownImg)));
        sectionTools.append(button(section.hasAttribute("data-gt-hidden") ? "👁 Megjelenítés" : "🚫 Elrejtés", () => toggleHidden(section, id)));
        const rect = section.getBoundingClientRect();
        sectionTools.style.display = "flex";
        sectionTools.style.top = `${window.scrollY + Math.max(rect.top, headerOffset()) + 6}px`;
        sectionTools.style.left = `${window.scrollX + Math.max(rect.left, 0) + 8}px`;
      } else {
        sectionTools.style.display = "none";
      }
    }

    function hideTools() {
      tools.style.display = "none"; sectionTools.style.display = "none";
      document.querySelectorAll(".gt-ed-hover").forEach((e) => e.classList.remove("gt-ed-hover"));
      current = null; currentSection = null;
    }

    document.addEventListener("mouseover", (event) => {
      if (!editing) return;
      if (event.target.closest(".gt-ed-tools,.gt-ed-bar")) { clearTimeout(hideTimer); clearTimeout(toolsTimer); clearTimeout(retargetTimer); return; }
      const target = event.target.closest("[data-gt-item],[data-gt-link],[data-gt-text],img[data-gt-img],[data-gt-block]");
      clearTimeout(hideTimer);
      if (!target) { hideTimer = setTimeout(hideTools, 400); return; }
      if (event.target.closest("[data-gt-block]") !== currentSection) showSectionTools(event.target);
      const base = baseFor(event.target);
      // Empty space inside a section: keep the element panel and any pending switch.
      if (!base) return;
      if (tools.style.display !== "flex" || !current) { showToolsFor(event.target); return; }
      clearTimeout(retargetTimer);
      if (base === current) return;
      // While a panel is open, switch to another element only after the pointer rests on it,
      // so moving towards the panel across other elements does not retarget it.
      const pointed = event.target;
      retargetTimer = setTimeout(() => showToolsFor(pointed), 250);
    });
    // Scrolling only moves the open panels; it never changes their target.
    window.addEventListener("scroll", () => {
      if (!editing) return;
      if (current && tools.style.display === "flex") place(tools, current);
      if (currentSection && sectionTools.style.display === "flex") {
        const rect = currentSection.getBoundingClientRect();
        sectionTools.style.top = `${window.scrollY + Math.max(rect.top, headerOffset()) + 6}px`;
        sectionTools.style.left = `${window.scrollX + Math.max(rect.left, 0) + 8}px`;
      }
    }, { passive: true });

    // ---------- text editing events
    document.addEventListener("click", (event) => {
      if (!editing || event.target.closest(".gt-ed-bar,.gt-ed-tools")) return;
      // Only the editable content is protected from navigating/submitting (cookie banner etc. keep working).
      const editable = event.target.closest("[data-gt-block],[data-gt-item],[data-gt-text],[data-gt-link],footer,header.site-header");
      if (editable && event.target.closest("a,button,[data-gt-item],[data-gt-text],form,label")) {
        event.preventDefault();
        event.stopPropagation();
        showToolsFor(event.target);
      }
    }, true);
    document.addEventListener("submit", (event) => { if (editing && event.target.closest("[data-gt-block],footer")) event.preventDefault(); }, true);
    document.addEventListener("focusin", (event) => {
      const el = event.target.closest && event.target.closest(`[${TEXT}]`);
      if (el && editing) { focused = el; refresh(); }
    });
    document.addEventListener("input", (event) => {
      const el = event.target.closest && event.target.closest(`[${TEXT}]`);
      if (el && editing) markText(el);
    });
    document.addEventListener("keydown", (event) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "s" && editing) { event.preventDefault(); save(); return; }
      const el = event.target.closest && event.target.closest(`[${TEXT}]`);
      if (!el || !editing) return;
      if (event.key === "Enter") { event.preventDefault(); if (isRich(el)) document.execCommand("insertLineBreak"); }
      if (event.key === "Escape") el.blur();
    });
    document.addEventListener("paste", (event) => {
      const el = event.target.closest && event.target.closest(`[${TEXT}]`);
      if (!el || !editing) return;
      event.preventDefault();
      const text = (event.clipboardData || window.clipboardData).getData("text/plain");
      document.execCommand("insertText", false, isRich(el) ? text.replace(/\s*\n\s*/g, " ") : text);
    });
    window.addEventListener("beforeunload", (event) => {
      if (changeCount() && !leaving) { event.preventDefault(); event.returnValue = ""; }
    });

    function originalText(el) {
      const box = document.createElement("div");
      box.innerHTML = originalHtml(el);
      return isRich(el) ? sanitize(box.innerHTML) : box.textContent.replace(/\s+/g, " ").trim();
    }

    async function post(url, body) {
      const response = await fetch(url, {
        method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest" },
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));
      if (response.status === 401) throw new Error("Lejárt az admin bejelentkezés. Lépj be újra az /admin oldalon, majd frissítsd ezt az oldalt.");
      if (response.status === 419) throw new Error("Lejárt a munkamenet. Frissítsd az oldalt, és mentsd újra.");
      if (!response.ok) throw new Error(data.message || data.error || `Mentés sikertelen (hibakód: ${response.status}).`);
      return data;
    }

    async function save() {
      if (!changeCount()) return;
      $('[data-act="save"]').disabled = true;
      setStatus("Mentés…");
      try {
        if (dirtyLayouts.size) {
          const payload = {};
          dirtyLayouts.forEach((prefix) => { payload[prefix] = layoutFor(prefix); });
          await post(SAVE_LAYOUT, { page: location.pathname, layouts: payload });
          dirtyLayouts.clear();
        }
        if (textChanges.size) {
          const changes = {};
          textChanges.forEach((change, el) => {
            if (!document.contains(el) && !change.reset) return;
            const key = el.getAttribute(TEXT);
            changes[key] = change.reset ? { reset: true }
              : { value: isRich(el) ? sanitize(el.innerHTML) : el.textContent.replace(/\s+/g, " ").trim(), rich: isRich(el), original: originalText(el) };
          });
          const data = Object.keys(changes).length ? await post(SAVE_TEXT, { page: location.pathname, changes }) : { saved: [] };
          textChanges.forEach((change, el) => {
            el.removeAttribute("data-gt-dirty");
            const key = el.getAttribute(TEXT);
            if (change.reset || !(data.saved || []).includes(key)) el.removeAttribute("data-gt-overridden"); else el.setAttribute("data-gt-overridden", "");
            $$(`[${TEXT}="${CSS.escape(key)}"]`).filter((other) => other !== el).forEach((other) => { other.innerHTML = el.innerHTML; });
          });
          textChanges.clear();
        }
        refresh();
        setStatus("Mentve ✓ – a látogatók frissítés után látják.");
      } catch (error) {
        setStatus(error.message || "Kapcsolódási hiba, próbáld újra.");
        $('[data-act="save"]').disabled = false;
      }
    }

    bar.addEventListener("click", (event) => {
      const action = event.target.closest("[data-act]")?.dataset.act;
      if (action === "toggle") {
        if (editing && changeCount() && !confirm("Van mentetlen módosítás. Biztosan kilépsz? (Nem kerül mentésre.)")) return;
        setEditing(!editing);
      }
      if (action === "save") save();
      if (action === "discard") {
        if (!confirm("Elveted az összes mentetlen módosítást? Az oldal újratöltődik.")) return;
        leaving = true;
        const url = new URL(location.href); url.searchParams.set("gt-edit", "1");
        location.href = url.toString();
      }
      if (action === "reset" && focused) {
        focused.innerHTML = originalHtml(focused);
        textChanges.set(focused, { reset: true });
        focused.setAttribute("data-gt-dirty", "");
        refresh();
      }
    });

    if (new URLSearchParams(location.search).has("gt-edit")) setEditing(true);
    refresh();
  }
})();
