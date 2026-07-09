const loginSection = document.querySelector("[data-admin-login]");
const dashboard = document.querySelector("[data-admin-dashboard]");
const loginForm = document.querySelector("[data-admin-login-form]");
let credentials = sessionStorage.getItem("goldenTorAdminAuth") || "";

async function adminFetch(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: { "Content-Type": "application/json", Authorization: `Basic ${credentials}`, ...(options.headers || {}) },
  });
  if (response.status === 401) throw new Error("Hibás felhasználónév vagy jelszó.");
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
  credentials = btoa(`${data.get("username")}:${data.get("password")}`);
  const status = document.querySelector("[data-admin-login-status]");
  try {
    await enterDashboard();
    sessionStorage.setItem("goldenTorAdminAuth", credentials);
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
    if (button.dataset.adminTab === "overview") await loadStats();
  });
});

document.querySelectorAll("[data-admin-refresh]").forEach((button) => button.addEventListener("click", async () => {
  const panel = button.closest("[data-admin-panel]").dataset.adminPanel;
  if (panel === "bookings") await loadBookings();
  if (panel === "contacts") await loadContacts();
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

document.querySelector("[data-expert-template]").addEventListener("click", () => {
  const form = document.querySelector("[data-content-form]");
  form.elements.contentKey.value = "experts";
  form.elements.title.value = "Golden Tor szakértői profilok";
  form.elements.payload.value = JSON.stringify({ profiles: [{ name: "", role: "", bio: "", credentials: [""], photo: "assets/szakerto-neve.webp" }] }, null, 2);
});

document.querySelector("[data-admin-logout]").addEventListener("click", () => {
  sessionStorage.removeItem("goldenTorAdminAuth"); location.reload();
});

if (credentials) enterDashboard().catch(() => sessionStorage.removeItem("goldenTorAdminAuth"));
