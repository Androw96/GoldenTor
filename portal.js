const params = new URLSearchParams(window.location.search);
const token = params.get("token");
const loading = document.querySelector("[data-portal-loading]");
const content = document.querySelector("[data-portal-content]");
const message = document.querySelector("[data-portal-message]");
let booking;

const statusNames = {
  requested: "Visszaigazolásra vár",
  confirmed: "Visszaigazolva",
  completed: "Lezárva",
  cancelled: "Lemondva",
};

function googleCalendarUrl(item) {
  const start = `${item.booking_date.replaceAll("-", "")}T${item.booking_time.replace(":", "")}00`;
  const startDate = new Date(`${item.booking_date}T${item.booking_time}:00`);
  const endDate = new Date(startDate.getTime() + 45 * 60 * 1000);
  const end = `${endDate.getFullYear()}${String(endDate.getMonth() + 1).padStart(2, "0")}${String(endDate.getDate()).padStart(2, "0")}T${String(endDate.getHours()).padStart(2, "0")}${String(endDate.getMinutes()).padStart(2, "0")}00`;
  return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(`Golden Tor konzultáció - ${item.topic}`)}&dates=${start}/${end}&details=${encodeURIComponent(item.meeting)}`;
}

async function loadBooking() {
  if (!token) throw new Error("Hiányzik a foglalási azonosító.");
  const response = await fetch(`/api/booking?token=${encodeURIComponent(token)}`);
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || "A foglalás nem található.");
  booking = result;
  document.querySelector("[data-portal-status]").textContent = statusNames[result.status] || result.status;
  document.querySelector("[data-portal-status]").dataset.status = result.status;
  document.querySelector("[data-portal-topic]").textContent = result.topic;
  document.querySelector("[data-portal-date]").textContent = `${result.booking_date} ${result.booking_time}`;
  document.querySelector("[data-portal-meeting]").textContent = result.meeting;
  document.querySelector("[data-calendar-download]").href = `/api/calendar.ics?token=${encodeURIComponent(token)}`;
  document.querySelector("[data-google-calendar]").href = googleCalendarUrl(result);
  document.querySelector("[data-cancel-booking]").hidden = !["requested", "confirmed"].includes(result.status);
  loading.hidden = true; content.hidden = false;
}

document.querySelector("[data-cancel-booking]").addEventListener("click", async () => {
  const button = document.querySelector("[data-cancel-booking]");
  button.disabled = true;
  const response = await fetch("/api/booking/cancel", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ token }) });
  const result = await response.json();
  if (!response.ok) { message.textContent = result.error || "A lemondás nem sikerült."; button.disabled = false; return; }
  message.textContent = "Az időpontkérést lemondtuk.";
  await loadBooking();
});

loadBooking().catch((error) => { loading.textContent = error.message; });
