const marketOffers = [
  { id: "allianz-home", provider: "Allianz", product: "Otthonom Plusz", types: ["home"], baseMonthly: 3890, coverageScore: 84, limit: "Épület, ingóság, assistance", extras: ["assistance", "digital", "fastClaim"], benefits: ["Vezetékes vízkár", "Lakásassistance", "Családi felelősségbiztosítás"], caveats: ["Nagy értékű ingóságnál bővítés szükséges."] },
  { id: "generali-home", provider: "Generali", product: "Házőrző Extra", types: ["home"], baseMonthly: 4180, coverageScore: 88, limit: "Épület és ingóság prémium fedezettel", extras: ["assistance", "digital", "premiumCare"], benefits: ["Betöréses lopás", "Üvegkár", "Prémium assistance"], caveats: ["Magasabb fedezet mellett magasabb díjszint várható."] },
  { id: "kh-home", provider: "K&H Biztosító", product: "Biztos Otthon", types: ["home"], baseMonthly: 4010, coverageScore: 85, limit: "Háztartási ingóság és baleseti kiegészítő", extras: ["digital", "fastClaim"], benefits: ["Épületbiztosítás", "Baleseti kiegészítő", "Online ügyintézés"], caveats: ["Assistance csomag feltételeit külön ellenőrizni kell."] },
  { id: "posta-home", provider: "Magyar Posta Biztosító", product: "PostaOtthon", types: ["home"], baseMonthly: 3370, coverageScore: 73, limit: "Kedvező alap lakásvédelem", extras: ["digital"], benefits: ["Elemi károk", "Vízkár", "Országos ügyintézés"], caveats: ["Komplex vagyonhoz könnyen alulfedezett lehet."] },
  { id: "allianz-casco", provider: "Allianz", product: "Autóm Casco", types: ["casco"], baseMonthly: 12800, coverageScore: 84, limit: "10%, minimum 100 000 Ft önrész", extras: ["digital", "fastClaim"], benefits: ["Töréskár", "Lopáskár", "Elemi kár"], caveats: ["A jármű pontos értéke módosíthatja a díjat."] },
  { id: "generali-casco", provider: "Generali", product: "Full Casco", types: ["casco"], baseMonthly: 13900, coverageScore: 90, limit: "Széles casco fedezet", extras: ["assistance", "digital", "fastClaim", "premiumCare"], benefits: ["Assistance", "Csereautó opció", "Vandalizmus"], caveats: ["Nem a legalacsonyabb díjszint."] },
  { id: "union-casco", provider: "Union", product: "Menta Casco", types: ["casco"], baseMonthly: 11900, coverageScore: 79, limit: "Moduláris casco fedezet", extras: ["assistance", "digital"], benefits: ["Üvegkár", "Vandalizmus", "0-24 assistance"], caveats: ["Egyes extra fedezetek pótdíjasak lehetnek."] },
  { id: "allianz-kgfb", provider: "Allianz", product: "Start KGFB", types: ["kgfb"], baseMonthly: 4850, coverageScore: 72, limit: "Jogszabály szerinti KGFB limit", extras: ["digital", "fastClaim"], benefits: ["Online szerződéskezelés", "Gyors kárbejelentés"], caveats: ["Assistance külön kiegészítőként érhető el."] },
  { id: "groupama-kgfb", provider: "Groupama", product: "Komfort KGFB", types: ["kgfb"], baseMonthly: 5290, coverageScore: 78, limit: "KGFB választható assistance-szal", extras: ["assistance", "digital"], benefits: ["0-24 assistance opció", "Digitális dokumentumtár"], caveats: ["Éves fizetéssel lehet kedvezőbb."] },
  { id: "kobe-kgfb", provider: "KÖBE", product: "Standard KGFB", types: ["kgfb"], baseMonthly: 4590, coverageScore: 67, limit: "Alap KGFB fedezet", extras: ["digital"], benefits: ["Alacsony alapdíj", "Egyszerű fedezet"], caveats: ["Szolgáltatási oldalról szerényebb csomag."] },
  { id: "colonnade-travel", provider: "Colonnade", product: "Travel Smart", types: ["travel"], baseMonthly: 6200, coverageScore: 82, limit: "Orvosi limit: 60 M Ft", extras: ["assistance", "digital"], benefits: ["Külföldi sürgősségi ellátás", "Poggyászfedezet"], caveats: ["Extrém sport pótdíjas lehet."] },
  { id: "uniqa-travel", provider: "UNIQA", product: "Utas Családi", types: ["travel"], baseMonthly: 7800, coverageScore: 88, limit: "Orvosi limit: 100 M Ft", extras: ["assistance", "digital", "fastClaim", "premiumCare"], benefits: ["Magas egészségügyi limit", "Családi kedvezmény"], caveats: ["Rövid utaknál drágább lehet."] },
  { id: "signal-health", provider: "Signal Iduna", product: "Egészség Core", types: ["health"], baseMonthly: 9900, coverageScore: 76, limit: "Éves diagnosztikai limit: 350 000 Ft", extras: ["digital"], benefits: ["Szakorvosi időpont", "Alap diagnosztika"], caveats: ["Műtéti térítés nem alapfedezet."] },
  { id: "generali-health", provider: "Generali", product: "Private Care", types: ["health"], baseMonthly: 15900, coverageScore: 89, limit: "Éves diagnosztikai limit: 900 000 Ft", extras: ["digital", "fastClaim", "premiumCare"], benefits: ["Bővebb diagnosztika", "Második orvosi vélemény"], caveats: ["Krónikus előzményeknél egyedi elbírálás lehet."] },
  { id: "union-health", provider: "Union", product: "Egészség Plusz", types: ["health"], baseMonthly: 12900, coverageScore: 83, limit: "Éves diagnosztikai limit: 650 000 Ft", extras: ["digital", "fastClaim"], benefits: ["Szakorvosi hozzáférés", "Online időpontkezelés"], caveats: ["Egyes vizsgálatokhoz előzetes jóváhagyás kellhet."] },
];

const marketLabels = {
  home: "Lakásbiztosítás",
  casco: "Casco",
  kgfb: "KGFB",
  travel: "Utasbiztosítás",
  health: "Egészségbiztosítás",
};

function marketCurrency(value) {
  return new Intl.NumberFormat("hu-HU", {
    style: "currency",
    currency: "HUF",
    maximumFractionDigits: 0,
  }).format(value);
}

function marketMultiplier(offer, state) {
  let multiplier = 1;
  if (state.age < 25) multiplier += 0.18;
  if (state.age > 62 && ["travel", "health"].includes(state.type)) multiplier += 0.16;
  if (state.city === "Budapest" && ["kgfb", "casco", "home"].includes(state.type)) multiplier += 0.07;
  if (state.carAge === "new" && state.type === "casco") multiplier += 0.11;
  if (state.carAge === "old" && ["kgfb", "casco"].includes(state.type)) multiplier += 0.08;
  if (state.mileage === "high" && ["kgfb", "casco"].includes(state.type)) multiplier += 0.1;
  if (state.mileage === "low" && ["kgfb", "casco"].includes(state.type)) multiplier -= 0.04;
  if (state.homeValue === "high" && state.type === "home") multiplier += 0.28;
  if (state.homeValue === "low" && state.type === "home") multiplier -= 0.08;
  if (state.travelDays === "long" && state.type === "travel") multiplier += 0.52;
  if (state.travelDays === "short" && state.type === "travel") multiplier -= 0.32;

  const missingExtras = state.extras.filter((extra) => !offer.extras.includes(extra)).length;
  multiplier += missingExtras * 0.055;
  return Math.max(multiplier, 0.62);
}

function marketScore(offer, price, state) {
  const requestedExtras = state.extras.length || 1;
  const extraMatch = state.extras.filter((extra) => offer.extras.includes(extra)).length / requestedExtras;
  const valueScore = offer.coverageScore * 0.72 + extraMatch * 18 + (100000 / Math.max(price, 1)) * 0.42;
  if (state.priority === "coverage") return offer.coverageScore * 2 + extraMatch * 25 - price / 3000;
  if (state.priority === "cheapest") return -price;
  return valueScore;
}

function getMarketState(form) {
  return {
    type: form.elements.type.value,
    priority: form.elements.priority.value,
    age: Number(form.elements.age.value || 52),
    city: form.elements.city.value,
    homeValue: form.elements.homeValue?.value || "normal",
    carAge: form.elements.carAge?.value || "mid",
    mileage: form.elements.mileage?.value || "normal",
    travelDays: form.elements.travelDays?.value || "week",
    extras: [...form.querySelectorAll("input[name='extras']:checked")].map((input) => input.value),
  };
}

function reasonForMarketChoice(state, offer) {
  if (state.priority === "coverage") return `${offer.coverageScore}/100 fedezeti pontszám`;
  if (state.priority === "cheapest") return "legalacsonyabb becsült díj";
  return "legerősebb ár-érték arány";
}

function rankedMarketOffers(state) {
  return marketOffers
    .filter((offer) => offer.types.includes(state.type))
    .map((offer) => {
      const price = Math.round((offer.baseMonthly * marketMultiplier(offer, state)) / 10) * 10;
      const missingExtras = state.extras.filter((extra) => !offer.extras.includes(extra));
      return {
        ...offer,
        price,
        missingExtras,
        rankScore: marketScore(offer, price, state),
      };
    })
    .sort((a, b) => b.rankScore - a.rankScore);
}

function updateMarketFields(root, state) {
  root.querySelectorAll("[data-market-field]").forEach((field) => {
    const type = field.dataset.marketField;
    const visible =
      (type === "home" && state.type === "home") ||
      (type === "travel" && state.type === "travel") ||
      (type === "vehicle" && ["kgfb", "casco"].includes(state.type));
    field.hidden = !visible;
  });
}

function renderMarket(root) {
  const form = root.querySelector("[data-market-form]");
  const results = root.querySelector("[data-market-results]");
  const state = getMarketState(form);
  const ranked = rankedMarketOffers(state);
  const winner = ranked[0];
  updateMarketFields(root, state);

  root.querySelector("[data-market-winner]").textContent = winner ? `${winner.provider} ${winner.product}` : "-";
  root.querySelector("[data-market-price]").textContent = winner ? `${marketCurrency(winner.price)} / hó` : "-";
  root.querySelector("[data-market-reason]").textContent = winner ? reasonForMarketChoice(state, winner) : "-";

  results.innerHTML = ranked
    .map((offer, index) => {
      const missing = offer.missingExtras.length
        ? `${offer.missingExtras.length} elvárt extra hiányzik vagy pótdíjas`
        : "minden elvárt extra teljesül";
      return `
        <article class="market-card">
          <div class="market-rank">${index + 1}</div>
          <div>
            <span>${marketLabels[state.type]}</span>
            <h3>${offer.provider} ${offer.product}</h3>
            <p>${offer.limit}</p>
            <ul>${offer.benefits.map((benefit) => `<li>${benefit}</li>`).join("")}</ul>
          </div>
          <div class="market-card-score">
            <strong>${marketCurrency(offer.price)}</strong>
            <span>/ hó becsült díj</span>
            <small>${offer.coverageScore}/100 fedezet</small>
            <em>${missing}</em>
          </div>
        </article>
      `;
    })
    .join("");
}

function initInsuranceMarket() {
  const root = document.querySelector("[data-insurance-market]");
  if (!root) return;
  const form = root.querySelector("[data-market-form]");
  form.addEventListener("input", () => renderMarket(root));
  form.addEventListener("change", () => renderMarket(root));
  renderMarket(root);
}

initInsuranceMarket();
