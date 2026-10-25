/*
 * Golden Tor – editable page texts.
 *
 * 1. Every visitor: loads the saved texts (/api/content/page_text) and replaces the
 *    marked elements ([data-gt-text]) on Hungarian pages. Unsaved elements keep the
 *    text from the HTML, so nothing disappears if the API is unavailable.
 * 2. Logged-in admins (Filament session): shows a "Szövegek szerkesztése" button.
 *    In edit mode every marked text can be clicked and rewritten in place, then saved
 *    to /admin/inline-editor/texts (CSRF protected, sanitised again on the server).
 */
(() => {
  const ATTR = "data-gt-text";
  const API = "/api/content/page_text?lang=hu";
  const SESSION = "/admin/inline-editor/session";
  const SAVE = "/admin/inline-editor/texts";
  const ALLOWED = {
    A: ["href", "target", "rel", "class"], STRONG: ["class"], EM: ["class"], B: [], I: [], BR: [],
    SPAN: ["class", "aria-hidden"], SMALL: ["class"], SUP: [], SUB: [],
  };

  const language = (() => { try { return localStorage.getItem("goldenTorLanguage") || "hu"; } catch { return "hu"; } })();
  const elements = () => [...document.querySelectorAll(`[${ATTR}]`)];
  const isRich = (el) => el.hasAttribute("data-gt-rich");
  const originals = new Map();
  elements().forEach((el) => originals.set(el, el.innerHTML));
  let saved = {};

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
          // DIV/P inserted by the browser on Enter become line breaks; anything else is unwrapped.
          if (["DIV", "P"].includes(node.tagName) && node.previousSibling) node.before(document.createElement("br"));
          node.replaceWith(...node.childNodes);
          return;
        }
        [...node.attributes].forEach((attribute) => {
          if (!allowed.includes(attribute.name)) node.removeAttribute(attribute.name);
        });
        if (node.tagName === "A" && node.hasAttribute("href")
          && !/^(https?:|mailto:|tel:|\/|#|[\w.-]+\.html)/i.test(node.getAttribute("href").trim())) {
          node.removeAttribute("href");
        }
      });
    };
    clean(template.content);
    return template.innerHTML.trim();
  }

  function apply(values) {
    if (language !== "hu" || !values || typeof values !== "object") return;
    saved = values;
    elements().forEach((el) => {
      const entry = values[el.getAttribute(ATTR)];
      if (!entry || typeof entry.v !== "string") return;
      if (isRich(el)) el.innerHTML = sanitize(entry.v);
      else el.textContent = entry.v;
      el.setAttribute("data-gt-overridden", "");
    });
  }

  const ready = fetch(API, { credentials: "same-origin" })
    .then((response) => (response.ok ? response.json() : null))
    .then((data) => apply(data && data.payload))
    .catch(() => {});

  // ---------------------------------------------------------------- editor
  const hasEditorHint = () => /(?:^|;\s*)gt_editor=1(?:;|$)/.test(document.cookie);
  if (!hasEditorHint() && !new URLSearchParams(location.search).has("gt-edit")) return;

  ready.then(() => fetch(SESSION, { credentials: "same-origin", headers: { Accept: "application/json" } }))
    .then((response) => (response && response.ok ? response.json() : null))
    .then((session) => {
      if (!session || !session.editor) {
        document.cookie = "gt_editor=; Max-Age=0; path=/; SameSite=Lax";
        return;
      }
      mountEditor(session.csrf);
    })
    .catch(() => {});

  function mountEditor(csrf) {
    const style = document.createElement("style");
    style.textContent = `
      .gt-ed-bar{position:fixed;right:16px;bottom:16px;z-index:2147483000;display:flex;flex-wrap:wrap;gap:8px;align-items:center;
        max-width:calc(100vw - 32px);padding:10px 12px;border-radius:12px;background:#141c17;color:#f8f3ee;
        font:600 14px/1.3 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.45);border:1px solid #2f4136}
      .gt-ed-bar button{all:unset;cursor:pointer;padding:8px 12px;border-radius:8px;background:#24382d;color:#f8f3ee;font:inherit}
      .gt-ed-bar button:hover{background:#2f4a3b}
      .gt-ed-bar button[disabled]{opacity:.45;cursor:default}
      .gt-ed-bar .gt-ed-primary{background:#b8962e;color:#141c17}
      .gt-ed-bar .gt-ed-primary:hover{background:#cfac45}
      .gt-ed-bar .gt-ed-status{font-weight:500;color:#cdd8d0;padding:0 4px}
      body.gt-ed-on [${ATTR}]{outline:1px dashed rgba(235,241,80,.55);outline-offset:3px;cursor:text}
      body.gt-ed-on [${ATTR}]:hover{outline:2px solid #ebf150}
      body.gt-ed-on [${ATTR}]:focus{outline:2px solid #ebf150;box-shadow:0 0 0 6px rgba(235,241,80,.18)}
      body.gt-ed-on [${ATTR}][data-gt-dirty]{outline:2px solid #4ade80}
    `;
    document.head.append(style);

    const bar = document.createElement("div");
    bar.className = "gt-ed-bar";
    bar.setAttribute("role", "toolbar");
    bar.setAttribute("aria-label", "Szövegszerkesztő");
    bar.innerHTML = `
      <button type="button" data-act="toggle" class="gt-ed-primary">✎ Szövegek szerkesztése</button>
      <span class="gt-ed-status" data-status hidden></span>
      <button type="button" data-act="save" class="gt-ed-primary" hidden disabled>Mentés</button>
      <button type="button" data-act="discard" hidden disabled>Elvetés</button>
      <button type="button" data-act="reset" hidden disabled title="A kijelölt szöveg visszaállítása az eredetire">Eredeti visszaállítása</button>
      <a href="/admin/teljes-szovegek" style="color:#d6b85a;padding:0 4px" data-admin-link hidden>Lista az adminban</a>`;
    document.body.append(bar);

    const $ = (selector) => bar.querySelector(selector);
    const status = $("[data-status]");
    const changes = new Map(); // element -> {reset?: true}
    let editing = false;
    let focused = null;

    const setStatus = (text) => { status.hidden = !text; status.textContent = text || ""; };
    const refresh = () => {
      const count = changes.size;
      $('[data-act="save"]').disabled = count === 0;
      $('[data-act="discard"]').disabled = count === 0;
      $('[data-act="reset"]').disabled = !focused
        || !(focused.hasAttribute("data-gt-overridden") || changes.has(focused));
      if (editing) setStatus(count ? `${count} módosítás nincs mentve` : "Kattints egy szövegre az átíráshoz");
    };

    function setEditing(on) {
      editing = on;
      document.body.classList.toggle("gt-ed-on", on);
      $('[data-act="toggle"]').textContent = on ? "Kilépés a szerkesztésből" : "✎ Szövegek szerkesztése";
      $('[data-act="toggle"]').classList.toggle("gt-ed-primary", !on);
      ["save", "discard", "reset"].forEach((act) => { $(`[data-act="${act}"]`).hidden = !on; });
      $("[data-admin-link]").hidden = !on;
      elements().forEach((el) => {
        if (!on) { el.removeAttribute("contenteditable"); return; }
        if (isRich(el)) { el.contentEditable = "true"; return; }
        el.contentEditable = "plaintext-only";
        if (el.contentEditable !== "plaintext-only") el.contentEditable = "true";
      });
      if (!on) setStatus("");
      refresh();
    }

    function markDirty(el) {
      changes.set(el, {});
      el.setAttribute("data-gt-dirty", "");
      refresh();
    }

    // Clicking links/buttons must not navigate or submit while editing.
    document.addEventListener("click", (event) => {
      if (editing && event.target.closest(`[${ATTR}]`) && !event.target.closest(".gt-ed-bar")) {
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);
    document.addEventListener("focusin", (event) => {
      const el = event.target.closest && event.target.closest(`[${ATTR}]`);
      if (el && editing) { focused = el; refresh(); }
    });
    document.addEventListener("input", (event) => {
      const el = event.target.closest && event.target.closest(`[${ATTR}]`);
      if (el && editing) markDirty(el);
    });
    document.addEventListener("keydown", (event) => {
      const el = event.target.closest && event.target.closest(`[${ATTR}]`);
      if (!el || !editing) return;
      if (event.key === "Enter") {
        event.preventDefault();
        if (isRich(el)) document.execCommand("insertLineBreak");
      }
      if (event.key === "Escape") el.blur();
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "s") { event.preventDefault(); save(); }
    });
    document.addEventListener("paste", (event) => {
      const el = event.target.closest && event.target.closest(`[${ATTR}]`);
      if (!el || !editing) return;
      event.preventDefault();
      const text = (event.clipboardData || window.clipboardData).getData("text/plain");
      document.execCommand("insertText", false, isRich(el) ? text.replace(/\s*\n\s*/g, " ") : text);
    });
    window.addEventListener("beforeunload", (event) => {
      if (changes.size) { event.preventDefault(); event.returnValue = ""; }
    });

    function originalText(el) {
      const box = document.createElement("div");
      box.innerHTML = originals.get(el) || "";
      return isRich(el) ? sanitize(box.innerHTML) : box.textContent.replace(/\s+/g, " ").trim();
    }

    async function save() {
      if (!changes.size) return;
      const payload = {};
      changes.forEach((change, el) => {
        const key = el.getAttribute(ATTR);
        payload[key] = change.reset
          ? { reset: true }
          : { value: isRich(el) ? sanitize(el.innerHTML) : el.textContent.replace(/\s+/g, " ").trim(), rich: isRich(el), original: originalText(el) };
      });
      $('[data-act="save"]').disabled = true;
      setStatus("Mentés…");
      try {
        const response = await fetch(SAVE, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest" },
          body: JSON.stringify({ page: location.pathname, changes: payload }),
        });
        const data = await response.json().catch(() => ({}));
        if (response.status === 401) throw new Error("Lejárt az admin bejelentkezés. Lépj be újra az /admin oldalon, majd frissítsd ezt az oldalt.");
        if (response.status === 419) throw new Error("Lejárt a munkamenet. Frissítsd az oldalt (a módosítások elvesznek), és mentsd újra.");
        if (!response.ok) throw new Error(data.message || data.error || `Mentés sikertelen (hibakód: ${response.status}).`);
        changes.forEach((change, el) => {
          el.removeAttribute("data-gt-dirty");
          const key = el.getAttribute(ATTR);
          if (change.reset || !(data.saved || []).includes(key)) el.removeAttribute("data-gt-overridden");
          else el.setAttribute("data-gt-overridden", "");
          // Same id elsewhere on the page (shared header/footer texts).
          elements().filter((other) => other !== el && other.getAttribute(ATTR) === key).forEach((other) => { other.innerHTML = el.innerHTML; });
        });
        changes.clear();
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
        if (editing && changes.size && !confirm("Van mentetlen módosítás. Biztosan kilépsz? (A módosítások az oldalon maradnak, de nem kerülnek mentésre.)")) return;
        setEditing(!editing);
      }
      if (action === "save") save();
      if (action === "discard") {
        changes.forEach((change, el) => {
          const entry = saved[el.getAttribute(ATTR)];
          if (entry && typeof entry.v === "string" && !change.reset) {
            if (isRich(el)) el.innerHTML = sanitize(entry.v); else el.textContent = entry.v;
          } else if (change.reset && entry) {
            if (isRich(el)) el.innerHTML = sanitize(entry.v); else el.textContent = entry.v;
            el.setAttribute("data-gt-overridden", "");
          } else {
            el.innerHTML = originals.get(el);
          }
          el.removeAttribute("data-gt-dirty");
        });
        changes.clear();
        refresh();
      }
      if (action === "reset" && focused) {
        focused.innerHTML = originals.get(focused);
        changes.set(focused, { reset: true });
        focused.setAttribute("data-gt-dirty", "");
        refresh();
      }
    });

    if (new URLSearchParams(location.search).has("gt-edit")) setEditing(true);
    refresh();
  }
})();
