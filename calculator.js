const money = new Intl.NumberFormat("hu-HU", { style: "currency", currency: "HUF", maximumFractionDigits: 0 });

const calculatorTranslations = {
  en: {
    tabs: ["Wealth building", "Loan", "Safety reserve"],
    headings: ["How much could your money build?", "What could the loan cost?", "What reserve could provide security?"],
    results: ["Estimated future value", "Estimated monthly payment", "Suggested reserve level"],
  },
  de: {
    tabs: ["Vermögensaufbau", "Kredit", "Sicherheitsreserve"],
    headings: ["Wie viel könnte Ihr Geld aufbauen?", "Was könnte der Kredit kosten?", "Welche Reserve könnte Sicherheit geben?"],
    results: ["Geschätzter Zukunftswert", "Geschätzte Monatsrate", "Empfohlene Reserve"],
  },
};

function translateCalculator() {
  const language = localStorage.getItem("goldenTorLanguage") || "hu";
  const values = calculatorTranslations[language];
  if (!values) return;
  document.querySelectorAll("[data-calculator-tab]").forEach((button, index) => { button.textContent = values.tabs[index]; });
  document.querySelectorAll(".calculator-form h2").forEach((heading, index) => { heading.textContent = values.headings[index]; });
  document.querySelectorAll(".calculator-result > span").forEach((label, index) => { label.textContent = values.results[index]; });
}

function numberValue(selector) {
  return Number(document.querySelector(selector)?.value || 0);
}

function calculateInvestment() {
  const capital = numberValue('[data-investment="capital"]');
  const monthly = numberValue('[data-investment="monthly"]');
  const rate = numberValue('[data-investment="rate"]');
  const years = numberValue('[data-investment="years"]');
  const monthlyRate = rate / 100 / 12;
  const periods = years * 12;
  const futureCapital = capital * ((1 + monthlyRate) ** periods);
  const futurePayments = monthlyRate ? monthly * (((1 + monthlyRate) ** periods - 1) / monthlyRate) : monthly * periods;
  const total = futureCapital + futurePayments;
  const paid = capital + monthly * periods;
  document.querySelector('[data-investment="rate"] + output').textContent = `${String(rate).replace(".", ",")}%`;
  document.querySelector('[data-investment="years"] + output').textContent = `${years} év`;
  document.querySelector("[data-investment-result]").textContent = money.format(total);
  document.querySelector("[data-investment-paid]").textContent = money.format(paid);
  document.querySelector("[data-investment-growth]").textContent = money.format(Math.max(0, total - paid));
}

function calculateLoan() {
  const amount = numberValue('[data-loan="amount"]');
  const rate = numberValue('[data-loan="rate"]');
  const years = numberValue('[data-loan="years"]');
  const periods = years * 12;
  const monthlyRate = rate / 100 / 12;
  const payment = monthlyRate ? amount * monthlyRate * ((1 + monthlyRate) ** periods) / (((1 + monthlyRate) ** periods) - 1) : amount / periods;
  const total = payment * periods;
  document.querySelector('[data-loan="rate"] + output').textContent = `${String(rate).replace(".", ",")}%`;
  document.querySelector('[data-loan="years"] + output').textContent = `${years} év`;
  document.querySelector("[data-loan-result]").textContent = money.format(payment);
  document.querySelector("[data-loan-total]").textContent = money.format(total);
  document.querySelector("[data-loan-cost]").textContent = money.format(Math.max(0, total - amount));
}

function calculateReserve() {
  const expense = numberValue('[data-reserve="expense"]');
  const dependants = numberValue('[data-reserve="dependants"]');
  const months = numberValue('[data-reserve="months"]');
  const current = numberValue('[data-reserve="current"]');
  const target = expense * months * (1 + dependants * 0.05);
  document.querySelector('[data-reserve="months"] + output').textContent = `${months} hónap`;
  document.querySelector("[data-reserve-result]").textContent = money.format(target);
  document.querySelector("[data-reserve-coverage]").textContent = expense ? `${(current / expense).toFixed(1).replace(".", ",")} hónap` : "-";
  document.querySelector("[data-reserve-gap]").textContent = money.format(Math.max(0, target - current));
}

document.querySelectorAll(".calculator-form input").forEach((input) => input.addEventListener("input", () => {
  calculateInvestment(); calculateLoan(); calculateReserve();
}));

document.querySelectorAll("[data-calculator-tab]").forEach((button) => button.addEventListener("click", () => {
  document.querySelectorAll("[data-calculator-tab]").forEach((item) => item.classList.toggle("active", item === button));
  document.querySelectorAll("[data-calculator-panel]").forEach((panel) => { panel.hidden = panel.dataset.calculatorPanel !== button.dataset.calculatorTab; });
  window.dataLayer?.push({ event: "calculator_opened", calculator: button.dataset.calculatorTab });
}));

calculateInvestment(); calculateLoan(); calculateReserve();
translateCalculator();
