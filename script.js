const header = document.querySelector("[data-header]");
const nav = document.querySelector("[data-nav]");
const toggle = document.querySelector("[data-menu-toggle]");
const links = [...document.querySelectorAll(".site-nav a")];
const currentPage = document.body.dataset.page;
window.dataLayer = window.dataLayer || [];

const sharedTranslations = {
  hu: { nav: ["Főoldal", "Befektetés", "Ingatlan", "Finanszírozás", "Biztosítás", "Szakértőink", "Blog", "Időpont"] },
  en: { nav: ["Home", "Investments", "Real Estate", "Financing", "Insurance", "Experts", "Journal", "Booking"] },
  de: { nav: ["Startseite", "Investitionen", "Immobilien", "Finanzierung", "Versicherung", "Experten", "Journal", "Termin"] },
};

const pageTranslations = {
  preindex: {
    en: ["Open the gate", "Investment, real estate, financing, insurance and expert decision support beyond a premium golden gate.", "Open gate"],
    de: ["Öffnen Sie das Tor", "Investitionen, Immobilien, Finanzierung, Versicherung und Expertenberatung hinter einem goldenen Tor.", "Tor öffnen"],
  },
  welcome: {
    en: ["Choose your direction", "Your next decision can open new opportunities in wealth building, real estate, financing, protection or expert analysis."],
    de: ["Wählen Sie Ihre Richtung", "Ihre nächste Entscheidung kann neue Möglichkeiten für Vermögensaufbau, Immobilien, Finanzierung, Schutz oder Expertenanalyse eröffnen."],
  },
  home: {
    en: ["Private financial decision support", "Wealth built on trust, calmer decisions.", "Investments, real estate, financing and insurance in one thoughtful system for clients who want discretion, clarity and a personal financial direction."],
    de: ["Private Finanzentscheidungen", "Vermögen mit Vertrauen aufbauen, ruhiger entscheiden.", "Investitionen, Immobilien, Finanzierung und Versicherung in einem durchdachten System für Kunden, die Diskretion, Klarheit und persönliche Orientierung suchen."],
  },
  blog: {
    en: ["Golden Tor Journal", "Financial decisions explained at a calmer pace.", "Short, clear articles for people who prefer good questions over loud promises."],
    de: ["Golden Tor Journal", "Finanzentscheidungen verständlich und in ruhigem Tempo.", "Kurze, klare Beiträge für Menschen, die gute Fragen lauten Versprechen vorziehen."],
  },
  befektetes: {
    en: ["Investment strategy", "Investments", "Give your wealth direction, rhythm and protection through a deliberate investment system."],
    de: ["Anlagestrategie", "Investitionen", "Geben Sie Ihrem Vermögen durch ein bewusstes Anlagesystem Richtung, Rhythmus und Schutz."],
  },
  ingatlan: {
    en: ["Real estate strategy", "Real Estate", "Premium real estate decisions aligned with value, location and investment potential."],
    de: ["Immobilienstrategie", "Immobilien", "Premium-Immobilienentscheidungen abgestimmt auf Wert, Lage und Investitionspotenzial."],
  },
  finanszirozas: {
    en: ["Financing solutions", "Financing", "A well-designed structure can accelerate goals while keeping financial obligations under control."],
    de: ["Finanzierungslösungen", "Finanzierung", "Eine gut aufgebaute Struktur kann Ziele beschleunigen und Belastungen kontrollierbar halten."],
  },
  biztositas: {
    en: ["Risk protection", "Insurance", "Independent protection strategy for family, business and accumulated wealth."],
    de: ["Risikoschutz", "Versicherung", "Unabhängige Schutzstrategie für Familie, Unternehmen und aufgebautes Vermögen."],
  },
  szakertoink: {
    en: ["Expert analysis", "Our Experts", "Identify hidden risks, missed potential and the next responsible strategic step."],
    de: ["Expertenanalyse", "Unsere Experten", "Erkennen Sie verborgene Risiken, ungenutztes Potenzial und den nächsten verantwortungsvollen Schritt."],
  },
  idopont: {
    en: ["Private consultation", "Book a consultation", "Choose a topic, meeting format and preferred time. Our team will confirm the final appointment."],
    de: ["Private Beratung", "Termin vereinbaren", "Wählen Sie Thema, Besprechungsform und Wunschtermin. Unser Team bestätigt den endgültigen Termin."],
  },
  kalkulatorok: {
    en: ["Decisions with numbers", "Financial calculators", "Three quick calculations to reveal the opportunity, monthly burden or safety gap behind your plans."],
    de: ["Entscheidungen mit Zahlen", "Finanzrechner", "Drei schnelle Berechnungen zeigen Chancen, monatliche Belastung oder Sicherheitslücke hinter Ihren Plänen."],
  },
};

function trackEvent(event, details = {}) {
  window.dataLayer.push({ event, ...details });
}

function translatePage(language) {
  document.documentElement.lang = language;
  const navLabels = sharedTranslations[language]?.nav;
  if (navLabels) links.forEach((link, index) => { if (navLabels[index]) link.textContent = navLabels[index]; });
  const values = pageTranslations[currentPage]?.[language];
  if (!values) return;
  let elements = [];
  if (currentPage === "preindex") elements = [document.querySelector(".gate-copy h1"), document.querySelector(".gate-copy p:not(.eyebrow)"), document.querySelector(".gate-label")];
  else if (currentPage === "welcome") elements = [document.querySelector(".welcome-intro h1"), document.querySelector(".welcome-intro p")];
  else if (currentPage === "home") elements = [document.querySelector(".hero-content .eyebrow"), document.querySelector(".hero-content h1"), document.querySelector(".hero-content p:not(.eyebrow)")];
  else if (currentPage === "blog") elements = [document.querySelector(".blog-hero .eyebrow"), document.querySelector(".blog-hero h1"), document.querySelector(".blog-hero p:not(.eyebrow)")];
  else if (currentPage === "idopont") elements = [document.querySelector(".booking-hero .eyebrow"), document.querySelector(".booking-hero h1"), document.querySelector(".booking-hero p:not(.eyebrow)")];
  else if (currentPage === "kalkulatorok") elements = [document.querySelector(".calculator-hero .eyebrow"), document.querySelector(".calculator-hero h1"), document.querySelector(".calculator-hero p:not(.eyebrow)")];
  else elements = [document.querySelector(".detail-hero .eyebrow"), document.querySelector(".detail-hero h1"), document.querySelector(".detail-hero p:not(.eyebrow)")];
  elements.forEach((element, index) => { if (element && values[index]) element.textContent = values[index]; });
}

function installLanguageSwitcher() {
  if (!["preindex", "welcome", "home", "befektetes", "ingatlan", "finanszirozas", "biztositas", "szakertoink", "blog", "idopont", "kalkulatorok"].includes(currentPage)) return;
  const switcher = document.createElement("div");
  switcher.className = `language-switcher${header ? "" : " floating-language"}`;
  switcher.setAttribute("aria-label", "Nyelvválasztás / Language / Sprache");
  ["hu", "en", "de"].forEach((language) => {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = language.toUpperCase();
    button.dataset.language = language;
    button.addEventListener("click", () => {
      localStorage.setItem("goldenTorLanguage", language);
      switcher.querySelectorAll("button").forEach((item) => item.classList.toggle("active", item === button));
      translatePage(language);
      if (currentPage === "kalkulatorok") window.location.reload();
      trackEvent("language_changed", { language });
    });
    switcher.append(button);
  });
  (header || document.body).append(switcher);
  const language = localStorage.getItem("goldenTorLanguage") || "hu";
  switcher.querySelector(`[data-language="${language}"]`)?.classList.add("active");
  translatePage(language);
}

function updateHeader() {
  if (header) header.classList.toggle("scrolled", window.scrollY > 18);
}

function closeMenu() {
  if (!nav || !toggle) return;
  nav.classList.remove("open");
  toggle.setAttribute("aria-expanded", "false");
}

if (nav && toggle) {
  toggle.addEventListener("click", () => {
    const isOpen = nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(isOpen));
  });
  links.forEach((link) => link.addEventListener("click", closeMenu));
}

if (currentPage) {
  links.forEach((link) => {
    link.classList.toggle("active", link.dataset.pageLink === currentPage);
  });
}

const sections = links
  .map((link) => {
    const href = link.getAttribute("href");
    return href && href.startsWith("#") ? document.querySelector(href) : null;
  })
  .filter(Boolean);

if (sections.length) {
  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) return;
      links.forEach((link) => {
        link.classList.toggle("active", link.getAttribute("href") === `#${visible.target.id}`);
      });
    },
    { rootMargin: "-30% 0px -55% 0px", threshold: [0.08, 0.3, 0.6] }
  );
  sections.forEach((section) => observer.observe(section));
}

function openPreparedEmail(subject, lines) {
  const body = lines.filter(Boolean).join("\n");
  window.location.href = `mailto:info@goldentor.hu?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.error || "A kérés nem sikerült.");
  return result;
}

const contactForm = document.querySelector("[data-contact-form]");
if (contactForm) {
  contactForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!contactForm.reportValidity()) return;
    const status = document.querySelector("[data-contact-status]");
    const data = new FormData(contactForm);
    const payload = Object.fromEntries(data.entries());
    if (status) status.textContent = "Küldés folyamatban...";
    try {
      await postJson("/api/contact", payload);
      trackEvent("contact_form_submitted", { topic: payload.topic });
      contactForm.reset();
      if (status) status.textContent = "Köszönjük! Megkeresését rögzítettük, hamarosan felvesszük Önnel a kapcsolatot.";
    } catch (error) {
      trackEvent("contact_form_fallback", { topic: payload.topic });
      if (status) status.textContent = "Az API nem érhető el, ezért az üzenetet a levelezőprogramjában készítettük elő.";
      openPreparedEmail("Golden Tor - új kapcsolatfelvétel", [
        `Név: ${payload.name}`, `E-mail: ${payload.email}`, `Telefon: ${payload.phone || "nincs megadva"}`,
        `Téma: ${payload.topic}`, "", "Üzenet:", payload.message,
      ]);
    }
  });
}

const bookingDate = document.querySelector("[data-booking-date]");
if (bookingDate) {
  const formatter = new Intl.DateTimeFormat("hu-HU", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  const date = new Date();
  let added = 0;
  while (added < 12) {
    date.setDate(date.getDate() + 1);
    const weekday = date.getDay();
    if (weekday === 0 || weekday === 6) continue;
    const value = date.toISOString().slice(0, 10);
    const option = document.createElement("option");
    option.value = value;
    option.textContent = formatter.format(date);
    bookingDate.append(option);
    added += 1;
  }
  bookingDate.addEventListener("change", async () => {
    const radios = [...document.querySelectorAll('[name="time"]')];
    radios.forEach((radio) => {
      radio.disabled = false;
      radio.closest("label").classList.remove("unavailable");
    });
    if (!bookingDate.value) return;
    try {
      const response = await fetch(`/api/availability?date=${encodeURIComponent(bookingDate.value)}`);
      if (!response.ok) return;
      const result = await response.json();
      result.slots.forEach((slot) => {
        const radio = document.querySelector(`[name="time"][value="${slot.time}"]`);
        if (radio && !slot.available) {
          radio.disabled = true;
          radio.checked = false;
          radio.closest("label").classList.add("unavailable");
        }
      });
    } catch (_) {
      // Static fallback: all listed slots remain selectable.
    }
  });
}

const bookingForm = document.querySelector("[data-booking-form]");
if (bookingForm) {
  const requestedExpert = new URLSearchParams(window.location.search).get("expert");
  if (requestedExpert) {
    const message = bookingForm.elements.message;
    if (message) message.value = `Kért szakértő: ${requestedExpert}`;
  }
  bookingForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!bookingForm.reportValidity()) return;
    const data = new FormData(bookingForm);
    const request = {
      name: data.get("name"),
      email: data.get("email"),
      phone: data.get("phone") || "nincs megadva",
      topic: data.get("topic"),
      meeting: data.get("meeting"),
      date: data.get("date"),
      time: data.get("time"),
      message: data.get("message") || "nincs megadva",
    };
    const status = document.querySelector("[data-booking-status]");
    request.website = data.get("website") || "";
    if (status) status.textContent = "Időpontkérés rögzítése...";
    try {
      const result = await postJson("/api/bookings", request);
      localStorage.setItem("goldenTorBookingRequest", JSON.stringify({ ...request, token: result.token }));
      trackEvent("booking_request_submitted", { topic: request.topic, meeting: request.meeting });
      bookingForm.reset();
      if (status) {
        status.textContent = "Időpontkérését rögzítettük. ";
        const portalLink = document.createElement("a");
        portalLink.href = result.portalUrl;
        portalLink.textContent = "Foglalás állapotának megnyitása";
        status.append(portalLink);
      }
    } catch (error) {
      if (status) status.textContent = `${error.message} Az adatokat a levelezőprogramjában készítettük elő.`;
      openPreparedEmail("Golden Tor - időpontkérés", [
        `Név: ${request.name}`, `E-mail: ${request.email}`, `Telefon: ${request.phone}`,
        `Téma: ${request.topic}`, `Találkozás: ${request.meeting}`,
        `Kért időpont: ${request.date} ${request.time}`, "", "Megjegyzés:", request.message,
      ]);
    }
  });
}

document.querySelectorAll(".site-footer").forEach((footer) => {
  if (footer.querySelector(".footer-links")) return;
  const linksWrapper = document.createElement("nav");
  linksWrapper.className = "footer-links";
  linksWrapper.setAttribute("aria-label", "Jogi és információs oldalak");
  linksWrapper.innerHTML = `
    <a href="impresszum.html">Impresszum</a>
    <a href="adatkezeles.html">Adatkezelés</a>
    <a href="jogi-nyilatkozat.html">Jogi nyilatkozat</a>
    <button type="button" data-cookie-settings>Cookie-beállítások</button>
  `;
  footer.append(linksWrapper);
});

function showCookieBanner() {
  let banner = document.querySelector("[data-cookie-banner]");
  if (!banner) {
    banner = document.createElement("section");
    banner.className = "cookie-banner";
    banner.dataset.cookieBanner = "";
    banner.setAttribute("aria-label", "Cookie-beállítások");
    banner.innerHTML = `
      <div>
        <strong>Adatvédelmi beállítások</strong>
        <p>Az oldal csak a működéshez szükséges helyi beállításokat használja. Marketing- és analitikai cookie jelenleg nincs bekapcsolva.</p>
      </div>
      <div class="cookie-actions">
        <a href="adatkezeles.html">Részletek</a>
        <button class="button button-gold" type="button" data-cookie-accept>Rendben</button>
      </div>
    `;
    document.body.append(banner);
    banner.querySelector("[data-cookie-accept]").addEventListener("click", () => {
      localStorage.setItem("goldenTorCookieChoice", "essential");
      banner.remove();
    });
  }
}

document.addEventListener("click", (event) => {
  if (event.target.closest("[data-cookie-settings]")) showCookieBanner();
});

if (!localStorage.getItem("goldenTorCookieChoice")) showCookieBanner();
installLanguageSwitcher();

async function loadExpertProfiles() {
  const container = document.querySelector("[data-expert-profiles]");
  if (!container) return;
  const language = localStorage.getItem("goldenTorLanguage") || "hu";
  try {
    const response = await fetch(`/api/content/experts?lang=${encodeURIComponent(language)}`);
    if (!response.ok) return;
    const result = await response.json();
    const profiles = Array.isArray(result.payload?.profiles) ? result.payload.profiles : [];
    const validProfiles = profiles.filter((profile) => profile.name && profile.role && profile.bio);
    if (!validProfiles.length) return;
    container.replaceChildren(...validProfiles.map((profile) => {
      const card = document.createElement("article");
      card.className = "expert-profile-card";
      if (profile.photo) {
        const image = document.createElement("img");
        image.src = profile.photo; image.alt = profile.name; image.loading = "lazy"; card.append(image);
      }
      const content = document.createElement("div");
      const role = document.createElement("span"); const name = document.createElement("h3"); const bio = document.createElement("p");
      role.textContent = profile.role; name.textContent = profile.name; bio.textContent = profile.bio; content.append(role, name, bio);
      if (Array.isArray(profile.credentials) && profile.credentials.length) {
        const list = document.createElement("ul");
        profile.credentials.forEach((credential) => { const item = document.createElement("li"); item.textContent = credential; list.append(item); });
        content.append(list);
      }
      const link = document.createElement("a");
      link.className = "button"; link.href = `idopont.html?expert=${encodeURIComponent(profile.name)}`; link.textContent = "Időpontot kérek";
      content.append(link); card.append(content); return card;
    }));
  } catch (_) {
    container.replaceChildren();
  }
}

loadExpertProfiles();

document.querySelectorAll(".opportunity-card").forEach((card) => {
  card.addEventListener("click", () => {
    trackEvent("welcome_direction_selected", { destination: card.getAttribute("href") });
  });
});

window.addEventListener("scroll", updateHeader, { passive: true });
updateHeader();
