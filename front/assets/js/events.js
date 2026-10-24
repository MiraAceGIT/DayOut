import { api, ApiError } from "./api.js";

const listEl  = document.querySelector("#events-list");
const errorEl = document.querySelector("#events-error");

function formatDate(iso) {
  if (!iso) return "";
  return new Date(iso).toLocaleString("fr-FR", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

function formatPrice(cents, isPaid) {
  if (!isPaid) return "Gratuit";
  return (Number(cents) / 100).toLocaleString("fr-FR", {
    style: "currency",
    currency: "EUR",
  });
}

// textContent uniquement (jamais innerHTML) : donnees API non fiables, protege du XSS.
function renderEvent(ev) {
  const article = document.createElement("article");
  article.className = "event-card";

  const title = document.createElement("h2");
  title.textContent = ev.title ?? "";
  article.appendChild(title);

  const meta = document.createElement("ul");
  meta.className = "event-meta";
  for (const text of [formatDate(ev.start_at), ev.city ?? "", null]) {
    if (text === null) continue;
    const li = document.createElement("li");
    li.textContent = text;
    meta.appendChild(li);
  }
  const priceLi = document.createElement("li");
  priceLi.className = "event-price";
  priceLi.textContent = formatPrice(ev.price_cents, ev.is_paid);
  meta.appendChild(priceLi);
  article.appendChild(meta);

  const desc = document.createElement("p");
  desc.className = "event-desc";
  desc.textContent = ev.description ?? "";
  article.appendChild(desc);

  const cap = document.createElement("p");
  cap.className = "event-cap";
  cap.textContent = `${ev.registered_count ?? 0} / ${ev.capacity ?? "∞"} inscrits`;
  article.appendChild(cap);

  return article;
}

function showStatus(message) {
  listEl.replaceChildren();
  const p = document.createElement("p");
  p.className = "status";
  p.textContent = message;
  p.setAttribute("role", "status");
  listEl.appendChild(p);
}

function showEvents(events) {
  listEl.replaceChildren();
  if (events.length === 0) {
    showStatus("Aucun evenement a afficher.");
    return;
  }
  const frag = document.createDocumentFragment();
  for (const ev of events) frag.appendChild(renderEvent(ev));
  listEl.appendChild(frag);
}

function showError(err) {
  if (err instanceof ApiError && err.type === "network") {
    errorEl.textContent = "Serveur injoignable. Verifie que l'API tourne.";
  } else if (err instanceof ApiError) {
    errorEl.textContent = `Erreur ${err.status} : ${err.message}`;
  } else {
    errorEl.textContent = "Erreur inattendue.";
    console.error(err);
  }
  listEl.replaceChildren();
}

async function loadEvents() {
  if (!listEl || !errorEl) return;
  errorEl.textContent = "";
  listEl.setAttribute("aria-busy", "true");
  showStatus("Chargement...");

  try {
    const { items } = await api.get("/api/events?limit=20");
    showEvents(items);
  } catch (err) {
    showError(err);
  } finally {
    listEl.setAttribute("aria-busy", "false");
  }
}

loadEvents();
