const loginSection = document.querySelector("[data-admin-login]");
const dashboard = document.querySelector("[data-admin-dashboard]");
const loginForm = document.querySelector("[data-admin-login-form]");
let adminKey = sessionStorage.getItem("goldenTorAdminKey") || "";
let siteTexts = {};
let blogPosts = [];

const defaultBlogPosts = [
  {
    category: "Befektetés",
    date: "2026. július",
    title: "Mit jelent, ha a pénznek nem csak helye, hanem iránya is van?",
    excerpt: "Sok megtakarítás azért veszít lendületet, mert nincs mögötte kimondott cél. Egy portfólió akkor válik valódi stratégiává, ha látható benne az időtáv, a kockázati határ, a likviditási tartalék és az a pont, ahol a döntést újra kell vizsgálni.",
    points: [
      "Miért nem elég egyetlen hozamelvárás alapján dönteni?",
      "Hogyan érdemes külön kezelni a biztonsági, növekedési és lehetőségkereső tőkét?",
      "Mikor jelez problémát, ha a portfólió túl bonyolult vagy túl egyszerű?",
    ],
  },
  {
    category: "Élethelyzet",
    date: "2026. július",
    title: "Öröklés, cégeladás vagy nagyobb bevétel után: mi legyen az első lépés?",
    excerpt: "Nagyobb vagyonmozgás után természetes, hogy gyors döntésekre érkeznek ajánlatok. A felelős sorrend mégis az, hogy először a célokat, adózási és családi szempontokat, majd a kockázatokat tisztázzuk.",
    points: [],
  },
  {
    category: "Finanszírozás",
    date: "2026. július",
    title: "Mikor segít a hitel, és mikor szűkíti be a mozgásteret?",
    excerpt: "A finanszírozás nem önmagában jó vagy rossz. A kérdés az, hogy a törlesztési struktúra mennyi szabadságot hagy váratlan helyzetekre, beruházásra vagy családi döntésekre.",
    points: [],
  },
  {
    category: "Biztosítás",
    date: "2026. július",
    title: "Vagyonvédelem: nem félelemből, hanem tervezésből",
    excerpt: "Egy biztosítási portfólió akkor hasznos, ha valós kockázatokra ad választ. A cél nem a túlbiztosítás, hanem annak felismerése, hol sérülhet a családi vagyon vagy vállalkozás folytonossága.",
    points: [],
  },
  {
    category: "Ingatlan",
    date: "2026. július",
    title: "Ingatlanvásárlás előtt: lokáció, likviditás, élethelyzet",
    excerpt: "Egy ingatlan értéke nem csak a négyzetméterárban látszik. Fontos, hogy mennyire könnyen értékesíthető, milyen finanszírozási terhet hoz, és hogyan illeszkedik a teljes vagyoni képbe.",
    points: [],
  },
];

const defaultDesign = {
  navy: "#1b251e",
  gold: "#668272",
  goldLight: "#ebf150",
  panel: "#263c2e",
  heroDarkness: "0.78",
  goldGlow: "0.14",
};

function cloneDefaultBlogPosts() {
  return JSON.parse(JSON.stringify(defaultBlogPosts));
}

function slugify(value) {
  return (value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

function normalizeBlogPost(post = {}, index = 0) {
  const title = (post.title || "Új blogcikk").trim();
  return {
    status: post.status === "draft" ? "draft" : "published",
    slug: (post.slug || slugify(title) || `blogcikk-${index + 1}`).trim(),
    category: (post.category || "Golden Tor").trim(),
    date: (post.date || "2026. július").trim(),
    image: (post.image || "").trim(),
    title,
    excerpt: (post.excerpt || "").trim(),
    points: Array.isArray(post.points) ? post.points.map((item) => String(item).trim()).filter(Boolean) : [],
    body: Array.isArray(post.body) ? post.body.map((item) => String(item).trim()).filter(Boolean) : [],
  };
}

const defaultSiteTexts = {
  home: {
    eyebrow: "Személyes pénzügyi tanácsadás",
    title: "Tisztán látni. Jól dönteni.",
    lead: "Segítünk átlátni, hogyan érdemes bánni a megtakarítással, ingatlannal, hitellel vagy biztosítással. Nem terméket erőltetünk, hanem érthető döntési sorrendet adunk.",
  },
  befektetes: {
    title: "Befektetés",
    lead: "Ne hagyja, hogy a pénze csak parkoljon. A befektetési stratégia célja, hogy a vagyonának iránya, ritmusa és védelme is legyen - nem találgatással, hanem tudatos rendszerben.",
  },
  ingatlan: {
    title: "Ingatlan",
    lead: "Az ingatlan akkor válik valódi vagyonelemmé, ha nem csak szép vagy jó helyen van, hanem illeszkedik a céljaihoz, finanszírozásához és hosszú távú stratégiájához.",
  },
  finanszirozas: {
    title: "Finanszírozás",
    lead: "A jó finanszírozás nem egyszerűen hitel. Olyan struktúra, amely gyorsítja a célokat, miközben kontroll alatt tartja a terheket, kockázatokat és mozgásteret.",
  },
  biztositas: {
    title: "Biztosítás",
    lead: "A biztosítás akkor értékes, ha pontosan arra ad választ, ami valóban veszélyeztetheti a családot, vállalkozást vagy felépített vagyont.",
  },
  szakertoink: {
    title: "Szakértőink",
    lead: "A szakértői átvilágítás célja, hogy ne maradjanak vakfoltok: lássa a rejtett kockázatokat, a kihagyott lehetőségeket és a következő felelős lépést.",
  },
  blog: {
    eyebrow: "Golden Tor Blog",
    title: "Pénzügyi döntések érthetően.",
    lead: "Rövid, áttekinthető írások azoknak, akik nagy döntések előtt nem zajt, hanem tiszta gondolatokat keresnek.",
  },
  idopont: {
    eyebrow: "Privát konzultáció",
    title: "Foglaljon időpontot",
    lead: "Válasszon témát, találkozási formát és egy Önnek megfelelő időpontot. A végleges időpontot munkatársunk visszaigazolja.",
  },
};

async function adminFetch(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: { "Content-Type": "application/json", "X-Admin-Key": adminKey, ...(options.headers || {}) },
  });
  if (response.status === 401) throw new Error("Hibás vagy hiányzó admin kód.");
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.error || "A kérés nem sikerült.");
  return result;
}

function textCell(row, value) {
  const cell = document.createElement("td");
  cell.textContent = value || "-";
  row.append(cell);
  return cell;
}

async function loadStats() {
  const result = await adminFetch("/api/admin/stats");
  const metrics = [
    ["Összes foglalás", result.bookings],
    ["Új érdeklődő", result.contacts],
    ["Közelgő időpont", result.upcoming],
    ["Havi foglalás", result.monthly],
    ["Visszaigazolási arány", `${result.conversion}%`],
  ];
  const grid = document.querySelector("[data-admin-kpis]");
  grid.replaceChildren(...metrics.map(([label, value]) => {
    const card = document.createElement("article");
    const strong = document.createElement("strong");
    const span = document.createElement("span");
    strong.textContent = value;
    span.textContent = label;
    card.append(strong, span);
    return card;
  }));
  const topics = document.querySelector("[data-admin-topics]");
  const max = Math.max(...result.topics.map((item) => item.count), 1);
  topics.replaceChildren(...result.topics.map((item) => {
    const row = document.createElement("div");
    row.className = "admin-topic-row";
    const label = document.createElement("span");
    label.textContent = `${item.topic} (${item.count})`;
    const bar = document.createElement("i");
    bar.style.width = `${Math.round(item.count / max * 100)}%`;
    row.append(label, bar);
    return row;
  }));
}

async function loadBookings() {
  const result = await adminFetch("/api/admin/bookings");
  const body = document.querySelector("[data-booking-rows]");
  body.replaceChildren();
  result.items.forEach((item) => {
    const row = document.createElement("tr");
    textCell(row, `${item.booking_date} ${item.booking_time}`);
    textCell(row, `${item.name}\n${item.email}\n${item.phone || ""}`);
    textCell(row, item.topic);
    textCell(row, item.meeting);
    const statusCell = document.createElement("td");
    const select = document.createElement("select");
    ["requested", "confirmed", "completed", "cancelled"].forEach((status) => {
      const option = new Option(status, status, false, status === item.status);
      select.add(option);
    });
    select.addEventListener("change", async () => {
      await adminFetch(`/api/admin/bookings/${item.id}`, { method: "PATCH", body: JSON.stringify({ status: select.value }) });
    });
    statusCell.append(select); row.append(statusCell); body.append(row);
  });
}

async function loadContacts() {
  const result = await adminFetch("/api/admin/contacts");
  const body = document.querySelector("[data-contact-rows]");
  body.replaceChildren();
  result.items.forEach((item) => {
    const row = document.createElement("tr");
    textCell(row, item.created_at);
    textCell(row, `${item.name}\n${item.email}\n${item.phone || ""}`);
    textCell(row, item.topic);
    textCell(row, item.message);
    const statusCell = document.createElement("td");
    const select = document.createElement("select");
    [
      ["new", "Új"],
      ["in_progress", "Folyamatban"],
      ["done", "Lezárva"],
      ["archived", "Archivált"],
    ].forEach(([value, label]) => {
      const option = new Option(label, value, false, value === item.status);
      select.add(option);
    });
    select.addEventListener("change", async () => {
      await adminFetch(`/api/admin/contacts/${item.id}`, { method: "PATCH", body: JSON.stringify({ status: select.value }) });
    });
    statusCell.append(select); row.append(statusCell);
    body.append(row);
  });
}

async function enterDashboard() {
  await loadStats();
  loginSection.hidden = true;
  dashboard.hidden = false;
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(loginForm);
  adminKey = String(data.get("adminKey") || "").trim();
  const status = document.querySelector("[data-admin-login-status]");
  try {
    await enterDashboard();
    sessionStorage.setItem("goldenTorAdminKey", adminKey);
    status.textContent = "";
  } catch (error) {
    status.textContent = error.message;
  }
});

document.querySelectorAll("[data-admin-tab]").forEach((button) => {
  button.addEventListener("click", async () => {
    document.querySelectorAll("[data-admin-tab]").forEach((item) => item.classList.toggle("active", item === button));
    document.querySelectorAll("[data-admin-panel]").forEach((panel) => { panel.hidden = panel.dataset.adminPanel !== button.dataset.adminTab; });
    if (button.dataset.adminTab === "bookings") await loadBookings();
    if (button.dataset.adminTab === "contacts") await loadContacts();
    if (button.dataset.adminTab === "feedback") await loadFeedback();
    if (button.dataset.adminTab === "texts") await loadSiteTexts();
    if (button.dataset.adminTab === "blog") await loadBlogPosts();
    if (button.dataset.adminTab === "design") await loadDesign();
    if (button.dataset.adminTab === "overview") await loadStats();
  });
});

document.querySelectorAll("[data-admin-refresh]").forEach((button) => button.addEventListener("click", async () => {
  const panel = button.closest("[data-admin-panel]").dataset.adminPanel;
  if (panel === "bookings") await loadBookings();
  if (panel === "contacts") await loadContacts();
  if (panel === "feedback") await loadFeedback();
  if (panel === "overview") await loadStats();
}));

document.querySelector("[data-content-form]").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const status = document.querySelector("[data-content-status]");
  try {
    const payload = JSON.parse(data.get("payload"));
    await adminFetch(`/api/admin/content/${encodeURIComponent(data.get("contentKey"))}/${encodeURIComponent(data.get("language"))}`, {
      method: "PUT", body: JSON.stringify({ title: data.get("title"), payload }),
    });
    status.textContent = "Tartalom mentve.";
  } catch (error) {
    status.textContent = error.message;
  }
});

async function loadSiteTexts() {
  const form = document.querySelector("[data-text-form]");
  const status = document.querySelector("[data-text-status]");
  const language = form.elements.language.value || "hu";
  try {
    const result = await fetch(`/api/content/site_texts?lang=${encodeURIComponent(language)}`);
    siteTexts = result.ok ? (await result.json()).payload || {} : {};
  } catch (_) {
    siteTexts = {};
  }
  fillTextForm();
  if (status) status.textContent = "Szövegek betöltve.";
}

function fillTextForm() {
  const form = document.querySelector("[data-text-form]");
  const page = form.elements.page.value;
  const value = siteTexts[page] || defaultSiteTexts[page] || {};
  form.elements.eyebrow.value = value.eyebrow || "";
  form.elements.title.value = value.title || "";
  form.elements.lead.value = value.lead || "";
}

document.querySelector("[data-text-form]").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const status = document.querySelector("[data-text-status]");
  const language = form.elements.language.value || "hu";
  const page = form.elements.page.value;
  siteTexts = { ...defaultSiteTexts, ...siteTexts };
  siteTexts[page] = {
    eyebrow: form.elements.eyebrow.value.trim(),
    title: form.elements.title.value.trim(),
    lead: form.elements.lead.value.trim(),
  };
  try {
    await adminFetch(`/api/admin/content/site_texts/${encodeURIComponent(language)}`, {
      method: "PUT",
      body: JSON.stringify({ title: "Weboldal szerkeszthető szövegek", payload: siteTexts }),
    });
    status.textContent = "Szöveg mentve. A nyilvános oldalon frissítés után látszik.";
  } catch (error) {
    status.textContent = error.message;
  }
});

document.querySelector("[data-text-page]").addEventListener("change", fillTextForm);
document.querySelector('[data-text-form] select[name="language"]').addEventListener("change", loadSiteTexts);
document.querySelector("[data-text-load]").addEventListener("click", loadSiteTexts);

async function loadBlogPosts() {
  const status = document.querySelector("[data-blog-status]");
  const language = document.querySelector('[data-blog-form] select[name="language"]').value || "hu";
  try {
    const result = await fetch(`/api/content/blog_posts?lang=${encodeURIComponent(language)}`);
    const payload = result.ok ? (await result.json()).payload : {};
    const posts = Array.isArray(payload.posts) && payload.posts.length ? payload.posts : cloneDefaultBlogPosts();
    blogPosts = posts.map(normalizeBlogPost);
  } catch (_) {
    blogPosts = cloneDefaultBlogPosts().map(normalizeBlogPost);
  }
  renderBlogPostSelect();
  fillBlogForm(0);
  if (status) status.textContent = "Blog betöltve.";
}

function renderBlogPostSelect() {
  const select = document.querySelector("[data-blog-post-select]");
  select.replaceChildren(...blogPosts.map((post, index) => {
    const state = post.status === "draft" ? " - vázlat" : "";
    return new Option(`${index + 1}. ${post.title || "Névtelen cikk"}${state}`, String(index));
  }));
}

function currentBlogIndex() {
  const value = Number(document.querySelector("[data-blog-post-select]").value || 0);
  return Number.isFinite(value) ? value : 0;
}

function fillBlogForm(index = currentBlogIndex()) {
  const form = document.querySelector("[data-blog-form]");
  const post = normalizeBlogPost(blogPosts[index] || {}, index);
  form.elements.postIndex.value = String(index);
  form.elements.status.value = post.status;
  form.elements.slug.value = post.slug;
  form.elements.category.value = post.category || "";
  form.elements.date.value = post.date || "";
  form.elements.image.value = post.image || "";
  form.elements.title.value = post.title || "";
  form.elements.excerpt.value = post.excerpt || "";
  form.elements.points.value = Array.isArray(post.points) ? post.points.join("\n") : "";
  form.elements.body.value = Array.isArray(post.body) ? post.body.join("\n\n") : "";
}

function collectBlogForm() {
  const form = document.querySelector("[data-blog-form]");
  const post = {
    status: form.elements.status.value === "draft" ? "draft" : "published",
    slug: form.elements.slug.value.trim(),
    category: form.elements.category.value.trim(),
    date: form.elements.date.value.trim(),
    image: form.elements.image.value.trim(),
    title: form.elements.title.value.trim(),
    excerpt: form.elements.excerpt.value.trim(),
    points: form.elements.points.value.split("\n").map((item) => item.trim()).filter(Boolean),
    body: form.elements.body.value.split(/\n{2,}/).map((item) => item.trim()).filter(Boolean),
  };
  return normalizeBlogPost(post, currentBlogIndex());
}

async function saveBlogPosts(message = "Blog mentve. A blog oldal frissítés után betölti.") {
  const form = document.querySelector("[data-blog-form]");
  const status = document.querySelector("[data-blog-status]");
  const selectedIndex = String(Math.min(currentBlogIndex(), Math.max(blogPosts.length - 1, 0)));
  blogPosts = blogPosts.map(normalizeBlogPost);
  await adminFetch(`/api/admin/content/blog_posts/${encodeURIComponent(form.elements.language.value || "hu")}`, {
    method: "PUT",
    body: JSON.stringify({ title: "Golden Tor blogbejegyzések", payload: { posts: blogPosts } }),
  });
  renderBlogPostSelect();
  document.querySelector("[data-blog-post-select]").value = selectedIndex;
  status.textContent = message;
}

document.querySelector("[data-blog-post-select]").addEventListener("change", () => fillBlogForm());
document.querySelector('[data-blog-form] select[name="language"]').addEventListener("change", loadBlogPosts);
document.querySelector("[data-blog-load]").addEventListener("click", loadBlogPosts);
document.querySelector("[data-blog-new]").addEventListener("click", () => {
  blogPosts.push(normalizeBlogPost({ title: "Új blogcikk", status: "draft", category: "", date: "", excerpt: "", points: [], body: [] }, blogPosts.length));
  renderBlogPostSelect();
  document.querySelector("[data-blog-post-select]").value = String(blogPosts.length - 1);
  fillBlogForm(blogPosts.length - 1);
  document.querySelector("[data-blog-status]").textContent = "Új vázlat létrehozva. Töltse ki, majd mentse.";
});
document.querySelector("[data-blog-duplicate]").addEventListener("click", () => {
  const source = collectBlogForm();
  const copy = normalizeBlogPost({ ...source, title: `${source.title} másolat`, slug: `${source.slug}-masolat`, status: "draft" }, blogPosts.length);
  blogPosts.push(copy);
  renderBlogPostSelect();
  document.querySelector("[data-blog-post-select]").value = String(blogPosts.length - 1);
  fillBlogForm(blogPosts.length - 1);
  document.querySelector("[data-blog-status]").textContent = "Cikk duplikálva vázlatként. Mentés után kerül a bloglistába.";
});
document.querySelector("[data-blog-delete]").addEventListener("click", async () => {
  const status = document.querySelector("[data-blog-status]");
  if (!blogPosts.length) return;
  const index = currentBlogIndex();
  const deletedTitle = blogPosts[index]?.title || "cikk";
  blogPosts.splice(index, 1);
  if (!blogPosts.length) blogPosts.push(normalizeBlogPost({ title: "Új blogcikk", status: "draft" }, 0));
  renderBlogPostSelect();
  document.querySelector("[data-blog-post-select]").value = String(Math.min(index, blogPosts.length - 1));
  fillBlogForm();
  try {
    await saveBlogPosts(`"${deletedTitle}" törölve.`);
  } catch (error) {
    status.textContent = error.message;
  }
});
document.querySelector("[data-blog-form]").addEventListener("submit", async (event) => {
  event.preventDefault();
  const status = document.querySelector("[data-blog-status]");
  blogPosts[currentBlogIndex()] = collectBlogForm();
  try {
    await saveBlogPosts();
  } catch (error) {
    status.textContent = error.message;
  }
});

async function loadDesign() {
  const form = document.querySelector("[data-design-form]");
  const status = document.querySelector("[data-design-status]");
  let design = defaultDesign;
  try {
    const response = await fetch("/api/content/site_design?lang=hu");
    if (response.ok) design = { ...defaultDesign, ...(await response.json()).payload };
  } catch (_) {
    design = defaultDesign;
  }
  Object.entries(design).forEach(([key, value]) => {
    if (form.elements[key]) form.elements[key].value = value;
  });
  if (status) status.textContent = "Design betöltve.";
}

document.querySelector("[data-design-load]").addEventListener("click", loadDesign);
document.querySelector("[data-design-reset]").addEventListener("click", () => {
  const form = document.querySelector("[data-design-form]");
  Object.entries(defaultDesign).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value; });
});
document.querySelector("[data-design-form]").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const status = document.querySelector("[data-design-status]");
  const payload = Object.fromEntries(new FormData(form).entries());
  try {
    await adminFetch("/api/admin/content/site_design/hu", {
      method: "PUT",
      body: JSON.stringify({ title: "Golden Tor design beállítások", payload }),
    });
    status.textContent = "Design mentve. Frissítés után érvényesül.";
  } catch (error) {
    status.textContent = error.message;
  }
});

document.querySelector("[data-expert-template]").addEventListener("click", () => {
  const form = document.querySelector("[data-content-form]");
  form.elements.contentKey.value = "experts";
  form.elements.title.value = "Golden Tor szakértői profilok";
  form.elements.payload.value = JSON.stringify({ profiles: [{ name: "", role: "", bio: "", credentials: [""], photo: "assets/szakerto-neve.webp" }] }, null, 2);
});

document.querySelector("[data-admin-logout]").addEventListener("click", () => {
  sessionStorage.removeItem("goldenTorAdminKey"); location.reload();
});

if (adminKey) enterDashboard().catch(() => sessionStorage.removeItem("goldenTorAdminKey"));

async function loadFeedback() {
  const status = document.querySelector('[data-feedback-admin-status]');
  try {
    const result = await adminFetch('/api/admin/feedback');
    document.querySelector('[data-feedback-rows]').replaceChildren(...result.items.map(item => {
      const row = document.createElement('tr');
      [item.created_at, `${item.name} (${item.email})`, item.expert, `${item.rating} / 5`, item.message].forEach(value => textCell(row, value));
      return row;
    }));
    status.textContent = result.items.length ? '' : 'Még nem érkezett visszajelzés.';
  } catch (error) { status.textContent = error.message; }
}
