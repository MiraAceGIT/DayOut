import { h } from "../../lib/dom.js";
import { loginUrl } from "../../core/routes.js";
import { renderEventRow } from "../components/eventRow/eventRow.js";
import { SEARCH_INPUT_ID } from "../layout/header/header.js";

const LOCALE = "fr-FR";
// Le compte est annonce par role=status : on attend la fin de la frappe / du glissement pour ne pas saturer.
const STATUS_DELAY_MS = 400;

function plural(n, word) {
  return `${n} ${word}${n > 1 ? "s" : ""}`;
}

/** Panneau gauche de la carte : onglets, point choisi, rayon, filtres, liste. Seul fichier qui connait ses ids. */
export class HomeView {
  constructor(root = document) {
    this.root = root;
    this.tabs        = [...root.querySelectorAll('#home-tabs [role="tab"]')];
    this.countEl     = root.querySelector("#home-nearby-count");
    this.pointEl     = root.querySelector("#home-point-label");
    this.changeBtn   = root.querySelector("#home-point-change");
    this.radiusEl    = root.querySelector("#home-radius");
    this.radiusOut   = root.querySelector("#home-radius-value");
    this.filtersEl   = root.querySelector("#home-filters");
    this.categoryEl  = root.querySelector("#home-categories");
    this.statusEl    = root.querySelector("#home-status");
    this.errorEl     = root.querySelector("#home-error");
    this.listEl      = root.querySelector("#home-list");
    this.loginLink   = root.querySelector("#home-login-link");
    this.searchEl    = root.querySelector(`#${SEARCH_INPUT_ID}`);
    this.handlers    = {};
    this.statusTimer = 0;
  }

  get isReady() {
    return Boolean(this.tabs.length && this.pointEl && this.radiusEl && this.radiusOut && this.filtersEl
      && this.categoryEl && this.statusEl && this.errorEl && this.listEl);
  }

  /** `handlers` : onSearch(q), onRadius(km), onFilter(name, on), onCategory(slug, on), onSelect(id), onChangePoint(). */
  bind(handlers) {
    this.handlers = handlers;
    this.bindTabs();
    if (this.loginLink) this.loginLink.href = loginUrl();

    this.radiusEl.addEventListener("input", () => handlers.onRadius(Number(this.radiusEl.value)));
    this.changeBtn?.addEventListener("click", () => handlers.onChangePoint());

    this.filtersEl.addEventListener("click", (e) => {
      const chip = e.target.closest("button[data-filter]");
      if (!chip) return;
      const on = this.toggle(chip);
      handlers.onFilter(chip.dataset.filter, on);
    });

    if (this.searchEl) {
      this.searchEl.addEventListener("input", () => {
        handlers.onSearch(this.searchEl.value);
        this.syncQueryToUrl(this.searchEl.value);
      });
      // Sur la carte, la recherche filtre en direct : pas de rechargement de page.
      this.searchEl.form?.addEventListener("submit", (e) => {
        e.preventDefault();
        handlers.onSearch(this.searchEl.value);
      });
    }
  }

  /** Garde ?q= a jour (lien partageable, retour arriere) sans recharger ni empiler d'historique. */
  syncQueryToUrl(query) {
    const url = new URL(location.href);
    if (query.trim()) url.searchParams.set("q", query);
    else url.searchParams.delete("q");
    history.replaceState(history.state, "", url);
  }

  /** Texte de la region live : immediat (chargement, erreur) ou differe (resultats). */
  setStatus(message, { delay = 0 } = {}) {
    clearTimeout(this.statusTimer);
    if (delay > 0) this.statusTimer = setTimeout(() => { this.statusEl.textContent = message; }, delay);
    else this.statusEl.textContent = message;
  }

  toggle(button) {
    const on = button.getAttribute("aria-pressed") !== "true";
    button.setAttribute("aria-pressed", String(on));
    return on;
  }

  /** Onglets ARIA : activation automatique, fleches gauche/droite, Debut/Fin, tabindex itinerant. */
  bindTabs() {
    const select = (tab, { focus = false } = {}) => {
      for (const t of this.tabs) {
        const active = t === tab;
        t.setAttribute("aria-selected", String(active));
        t.tabIndex = active ? 0 : -1;
        const panel = this.root.querySelector(`#${t.getAttribute("aria-controls")}`);
        if (panel) panel.hidden = !active;
      }
      if (focus) tab.focus();
    };

    for (const tab of this.tabs) {
      tab.addEventListener("click", () => select(tab));
      tab.addEventListener("keydown", (e) => {
        const i = this.tabs.indexOf(tab);
        const last = this.tabs.length - 1;
        const next = { ArrowRight: i === last ? 0 : i + 1, ArrowLeft: i === 0 ? last : i - 1, Home: 0, End: last }[e.key];
        if (next === undefined) return;
        e.preventDefault();
        select(this.tabs[next], { focus: true });
      });
    }
  }

  setQuery(query) {
    if (this.searchEl) this.searchEl.value = query;
  }

  /** `point` = { lat, lng, label? } : sans libelle, on affiche les coordonnees. */
  setOrigin(point) {
    const coords = `${point.lat.toLocaleString(LOCALE, { maximumFractionDigits: 4 })} ; `
      + `${point.lng.toLocaleString(LOCALE, { maximumFractionDigits: 4 })}`;
    this.pointEl.textContent = point.label || `Point sur la carte (${coords})`;
  }

  setRadius(km) {
    const min = Number(this.radiusEl.min) || 0;
    const max = Number(this.radiusEl.max) || 100;
    this.radiusEl.value = String(km);
    this.radiusEl.setAttribute("aria-valuetext", `${km} km`);
    this.radiusEl.style.setProperty("--range-progress", `${((km - min) / (max - min)) * 100}%`);
    this.radiusOut.textContent = `${km} km`;
  }

  /** Puces de categories construites depuis les donnees chargees (`categories` = [{ slug, label, key }]). */
  renderCategoryFilters(categories, active = new Set()) {
    this.categoryEl.replaceChildren(...categories.map((category) =>
      h("button", {
        type: "button",
        class: ["chip chip--category", category.key && `cat--${category.key}`],
        "aria-pressed": String(active.has(category.slug)),
        onClick: (e) => this.handlers.onCategory(category.slug, this.toggle(e.currentTarget)),
      },
        h("span", { class: "cat-dot", "aria-hidden": "true" }),
        category.label,
      )
    ));
    this.categoryEl.hidden = categories.length === 0;
  }

  showLoading() {
    this.errorEl.textContent = "";
    this.listEl.setAttribute("aria-busy", "true");
    this.setStatus("Chargement des événements…");
  }

  endLoading() {
    this.listEl.setAttribute("aria-busy", "false");
  }

  /** `result` = { items: [{ event, distanceKm, remaining }], inCircle, withoutPosition } ; annonce via role=status. */
  /** `loadedCap` : plafond de l'API atteint (nombre charge), des evenements a venir peuvent manquer ; 0 sinon. */
  showResults({ items, inCircle, withoutPosition, loadedCap = 0 }, selectedId = null) {
    this.listEl.replaceChildren(...items.map((item) => renderEventRow(item, {
      selected: item.event.id === selectedId,
      onSelect: (id) => this.handlers.onSelect(id),
    })));
    if (this.countEl) this.countEl.textContent = String(inCircle);

    let message = inCircle === 0
      ? "Aucun événement dans ce cercle"
      : `${plural(inCircle, "événement")} dans ce cercle`;
    if (withoutPosition > 0) message += ` · ${withoutPosition} sans position sur la carte`;
    if (loadedCap > 0) message += ` · au moins ${loadedCap} événements chargés, liste peut-être incomplète`;
    if (items.length === 0) message += ". Élargis le rayon ou retire des filtres.";
    this.setStatus(message, { delay: STATUS_DELAY_MS });
  }

  /** Synchronise la bordure orange ; `scroll` quand la selection vient de la carte. */
  markSelected(id, { scroll = false } = {}) {
    for (const btn of this.listEl.querySelectorAll(".event-row__select")) {
      const selected = btn.dataset.eventId === String(id);
      if (selected) {
        btn.setAttribute("aria-current", "true");
        if (scroll) {
          const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
          btn.closest(".event-row")?.scrollIntoView({ block: "nearest", behavior: reduce ? "auto" : "smooth" });
        }
      } else {
        btn.removeAttribute("aria-current");
      }
    }
  }

  showError(message) {
    this.listEl.replaceChildren();
    this.setStatus("");
    if (this.countEl) this.countEl.textContent = "0";
    this.errorEl.textContent = message;
  }
}
