const calculatorLanguage = (() => {
  try { return localStorage.getItem("goldenTorLanguage") || "hu"; } catch { return "hu"; }
})();

const money = new Intl.NumberFormat("hu-HU", { style: "currency", currency: "HUF", maximumFractionDigits: 0 });

const calculatorTranslations = {
  hu: {
    years: "év", months: "hónap", save: "Kalkuláció mentése", saving: "Mentés folyamatban…",
    saved: "Kalkuláció elmentve a személyes fiókjába.", loginNeeded: "A munkamenet lejárt. Jelentkezzen be újra a mentéshez.",
    saveFailed: "A kalkuláció mentése nem sikerült.",
  },
  en: {
    tabs: ["Wealth building", "Loan", "Safety reserve"],
    headings: ["How much could your money build?", "What could the loan cost?", "What reserve could provide security?"],
    results: ["Estimated future value", "Estimated monthly payment", "Suggested reserve level"],
    years: "years", months: "months", save: "Save calculation", saving: "Saving…",
    saved: "Calculation saved to your account.", loginNeeded: "Your session has expired. Please sign in again to save.",
    saveFailed: "The calculation could not be saved.",
  },
  de: {
    tabs: ["Vermögensaufbau", "Kredit", "Sicherheitsreserve"],
    headings: ["Wie viel könnte Ihr Geld aufbauen?", "Was könnte der Kredit kosten?", "Welche Reserve könnte Sicherheit geben?"],
    results: ["Geschätzter Zukunftswert", "Geschätzte Monatsrate", "Empfohlene Reserve"],
    years: "Jahre", months: "Monate", save: "Berechnung speichern", saving: "Wird gespeichert…",
    saved: "Berechnung in Ihrem Konto gespeichert.", loginNeeded: "Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.",
    saveFailed: "Die Berechnung konnte nicht gespeichert werden.",
  },
};

const calcText = (key) => (calculatorTranslations[calculatorLanguage] || calculatorTranslations.hu)[key] ?? calculatorTranslations.hu[key];

function translateCalculator() {
  const values = calculatorTranslations[calculatorLanguage];
  if (!values || !values.tabs) return;
  document.querySelectorAll("[data-calculator-tab]").forEach((button, index) => { button.textContent = values.tabs[index]; });
  document.querySelectorAll(".calculator-form h2").forEach((heading, index) => { heading.textContent = values.headings[index]; });
  document.querySelectorAll(".calculator-result > span").forEach((label, index) => { label.textContent = values.results[index]; });
}

/** Reads a numeric input and keeps it inside the input's own min/max (empty or invalid -> min, else 0). */
function numberValue(selector) {
  const input = document.querySelector(selector);
  if (!input) return 0;
  const min = input.min !== "" ? Number(input.min) : -Infinity;
  const max = input.max !== "" ? Number(input.max) : Infinity;
  const raw = String(input.value).replace(/\s/g, "").replace(",", ".");
  let value = raw === "" ? NaN : Number(raw);
  if (!Number.isFinite(value)) value = Number.isFinite(min) ? min : 0;
  return Math.min(max, Math.max(min, value));
}

const percent = (value) => `${String(value).replace(".", ",")}%`;
const setText = (selector, text) => { const element = document.querySelector(selector); if (element) element.textContent = text; };

function investmentModel() {
  const capital = numberValue('[data-investment="capital"]');
  const monthly = numberValue('[data-investment="monthly"]');
  const rate = numberValue('[data-investment="rate"]');
  const years = numberValue('[data-investment="years"]');
  const monthlyRate = rate / 100 / 12;
  const periods = years * 12;
  const growthFactor = (1 + monthlyRate) ** periods;
  const total = capital * growthFactor + (monthlyRate ? monthly * ((growthFactor - 1) / monthlyRate) : monthly * periods);
  const paid = capital + monthly * periods;
  return { inputs: { capital, monthly, rate, years }, results: { total, paid, growth: Math.max(0, total - paid) } };
}

function loanModel() {
  const amount = numberValue('[data-loan="amount"]');
  const rate = numberValue('[data-loan="rate"]');
  const years = numberValue('[data-loan="years"]');
  const periods = Math.max(1, years * 12);
  const monthlyRate = rate / 100 / 12;
  const factor = (1 + monthlyRate) ** periods;
  const payment = monthlyRate ? amount * monthlyRate * factor / (factor - 1) : amount / periods;
  const total = payment * periods;
  return { inputs: { amount, rate, years }, results: { payment, total, cost: Math.max(0, total - amount) } };
}

function reserveModel() {
  const expense = numberValue('[data-reserve="expense"]');
  const dependants = numberValue('[data-reserve="dependants"]');
  const months = numberValue('[data-reserve="months"]');
  const current = numberValue('[data-reserve="current"]');
  const target = expense * months * (1 + dependants * 0.05);
  return { inputs: { expense, dependants, months, current }, results: { target, coverage: expense ? current / expense : 0, gap: Math.max(0, target - current) } };
}

const models = { investment: investmentModel, loan: loanModel, reserve: reserveModel };

function renderCalculators() {
  const investment = investmentModel();
  setText('[data-investment="rate"] + output', percent(investment.inputs.rate));
  setText('[data-investment="years"] + output', `${investment.inputs.years} ${calcText("years")}`);
  setText("[data-investment-result]", money.format(investment.results.total));
  setText("[data-investment-paid]", money.format(investment.results.paid));
  setText("[data-investment-growth]", money.format(investment.results.growth));

  const loan = loanModel();
  setText('[data-loan="rate"] + output', percent(loan.inputs.rate));
  setText('[data-loan="years"] + output', `${loan.inputs.years} ${calcText("years")}`);
  setText("[data-loan-result]", money.format(loan.results.payment));
  setText("[data-loan-total]", money.format(loan.results.total));
  setText("[data-loan-cost]", money.format(loan.results.cost));

  const reserve = reserveModel();
  setText('[data-reserve="months"] + output', `${reserve.inputs.months} ${calcText("months")}`);
  setText("[data-reserve-result]", money.format(reserve.results.target));
  setText("[data-reserve-coverage]", reserve.inputs.expense ? `${reserve.results.coverage.toFixed(1).replace(".", ",")} ${calcText("months")}` : "-");
  setText("[data-reserve-gap]", money.format(reserve.results.gap));
}

document.querySelectorAll(".calculator-form input").forEach((input) => {
  input.addEventListener("input", renderCalculators);
  // On leaving a field, show the value that was actually used (clamped to the allowed range).
  input.addEventListener("change", () => {
    if (input.type !== "number") return;
    const key = Object.keys(input.dataset)[0];
    if (key) input.value = String(numberValue(`[data-${key}="${input.dataset[key]}"]`));
    renderCalculators();
  });
});

function selectCalculator(type) {
  document.querySelectorAll("[data-calculator-tab]").forEach((item) => {
    const active = item.dataset.calculatorTab === type;
    item.classList.toggle("active", active);
    item.setAttribute("aria-selected", String(active));
  });
  document.querySelectorAll("[data-calculator-panel]").forEach((panel) => { panel.hidden = panel.dataset.calculatorPanel !== type; });
}

document.querySelectorAll("[data-calculator-tab]").forEach((button) => button.addEventListener("click", () => {
  selectCalculator(button.dataset.calculatorTab);
  if (history.replaceState) history.replaceState(null, "", `#${button.dataset.calculatorTab}`);
  window.dataLayer?.push({ event: "calculator_opened", calculator: button.dataset.calculatorTab });
}));

function calculationPayload(type) {
  const model = (models[type] || reserveModel)();
  return { calculator_type: type, inputs: model.inputs, results: model.results };
}

async function saveCalculation(type, status, button) {
  button.disabled = true; status.textContent = calcText("saving");
  try {
    const response = await fetch("/api/calculations", {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-GoldenTor-Request": "1" },
      body: JSON.stringify(calculationPayload(type)),
    });
    let data = {};
    try { data = await response.json(); } catch { data = {}; }
    if (response.status === 401) throw new Error(calcText("loginNeeded"));
    if (!response.ok) throw new Error(data.error || `${calcText("saveFailed")} (${response.status})`);
    status.textContent = calcText("saved");
  } catch (error) {
    status.textContent = error.message || calcText("saveFailed");
  } finally {
    button.disabled = false;
  }
}

document.querySelectorAll("[data-calculator-panel]").forEach((panel) => {
  const type = panel.dataset.calculatorPanel;
  const result = panel.querySelector(".calculator-result");
  if (!result || result.querySelector(".calculation-save")) return;
  const button = document.createElement("button"); button.type = "button"; button.className = "button button-ghost calculation-save"; button.textContent = calcText("save");
  const status = document.createElement("p"); status.className = "calculation-save-status"; status.setAttribute("role", "status");
  button.addEventListener("click", () => saveCalculation(type, status, button));
  result.append(button, status);
});

renderCalculators();
translateCalculator();

const requestedCalculator = location.hash.slice(1);
if (Object.prototype.hasOwnProperty.call(models, requestedCalculator)) selectCalculator(requestedCalculator);
