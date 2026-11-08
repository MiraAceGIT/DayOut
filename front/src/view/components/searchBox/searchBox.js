import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { icon } from "../icon/icon.js";

// Prive a ce module : les autres views passent par SearchBox, jamais par l'id.
const INPUT_ID = "site-search";

/** Formulaire GET vers la carte : sans JS ou depuis une autre page, la recherche arrive en `?q=`. */
export function renderSearchBox() {
  return h("form", {
    class: "site-search",
    role: "search",
    action: routeUrl("/"),
    method: "get",
  },
    h("label", { class: "visually-hidden", for: INPUT_ID }, "Rechercher un événement ou un lieu"),
    icon("search", { className: "site-search__icon" }),
    h("input", {
      class: "site-search__input",
      id: INPUT_ID,
      type: "search",
      name: "q",
      placeholder: "Rechercher un événement, un lieu…",
      autocomplete: "off",
    }),
  );
}

/** A construire apres boot() ; sans champ, tout est sans effet. */
export class SearchBox {
  constructor(root = document) {
    this.inputEl = root.querySelector(`#${INPUT_ID}`);
  }

  setQuery(query) {
    if (this.inputEl) this.inputEl.value = query;
  }

  /** Le header reste affiche : `signal` retire ces ecouteurs quand on quitte la carte. */
  bindLive(onQuery, { signal } = {}) {
    if (!this.inputEl) return;
    this.inputEl.addEventListener("input", () => onQuery(this.inputEl.value), { signal });
    this.inputEl.form?.addEventListener("submit", (e) => {
      e.preventDefault();
      onQuery(this.inputEl.value);
    }, { signal });
  }
}
