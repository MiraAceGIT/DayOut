import { boot } from "../core/boot.js";
import { api } from "../core/api.js";
import { h } from "../lib/dom.js";
import { formatDate, formatPrice } from "../lib/format.js";
import { messageFor } from "../lib/forms.js";

boot({ page: "events" });

const listEl   = document.querySelector("#events-list");
const errorEl  = document.querySelector("#events-error");
const statusEl = document.querySelector("#events-status");

// Rendu via h() : les donnees API passent en noeuds texte, jamais en HTML.
function renderEvent(ev) {
  const when = formatDate(ev.start_at);

  return h("article", { class: "event-card" },
    h("h2", { class: "event-card__title" }, ev.title ?? ""),
    h("ul", { class: "event-card__meta" },
      when ? h("li", {}, h("time", { datetime: ev.start_at }, when)) : null,
      ev.city ? h("li", {}, ev.city) : null,
      h("li", { class: "event-card__price" },
        formatPrice(ev.price_cents, { isPaid: ev.is_paid, currency: ev.currency })),
    ),
    ev.description ? h("p", { class: "event-card__desc" }, ev.description) : null,
    h("p", { class: "event-card__capacity" },
      `${ev.registered_count ?? 0} / ${ev.capacity ?? "∞"} inscrits`),
  );
}

function setStatus(message) {
  statusEl.textContent = message;
}

function showEvents(events) {
  listEl.replaceChildren(...events.map(renderEvent));
  setStatus(events.length === 0
    ? "Aucun événement à afficher."
    : `${events.length} événement${events.length > 1 ? "s" : ""} à venir.`);
}

function showError(err) {
  if (err?.name !== "ApiError") console.error(err);
  listEl.replaceChildren();
  setStatus("");
  errorEl.textContent = messageFor(err);
}

async function loadEvents() {
  if (!listEl || !errorEl || !statusEl) return;
  errorEl.textContent = "";
  listEl.setAttribute("aria-busy", "true");
  setStatus("Chargement des événements…");

  try {
    const query = new URLSearchParams({ limit: "20" });
    const { items } = await api.get(`/api/events?${query}`);
    showEvents(Array.isArray(items) ? items : []);
  } catch (err) {
    showError(err);
  } finally {
    listEl.setAttribute("aria-busy", "false");
  }
}

loadEvents();
