import { h } from "../../../lib/dom.js";
import { frontUrl } from "../../../core/config.js";
import { icon } from "../../components/icon/icon.js";

// Prive a ce module : les autres views passent par SearchBox, jamais par l'id.
const INPUT_ID = "site-search";

/** Formulaire GET vers la carte : sans JS ou depuis une autre page, la recherche arrive en `?q=`. */
export function renderSearchBox() {
  return h("form", {
    class: "site-search",
    role: "search",
    action: frontUrl("index.html").href,
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

/** Pilotage du champ deja rendu par le header (a construire apres boot()). Sans champ, tout est sans effet. */
export class SearchBox {
  constructor(root = document) {
    this.inputEl = root.querySelector(`#${INPUT_ID}`);
  }

  setQuery(query) {
    if (this.inputEl) this.inputEl.value = query;
  }

  /** Recherche en direct : `onQuery(texte)` a chaque frappe et a l'envoi, qui ne recharge plus la page. */
  bindLive(onQuery) {
    if (!this.inputEl) return;
    this.inputEl.addEventListener("input", () => onQuery(this.inputEl.value));
    this.inputEl.form?.addEventListener("submit", (e) => {
      e.preventDefault();
      onQuery(this.inputEl.value);
    });
  }
}
