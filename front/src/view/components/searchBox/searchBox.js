import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { MAX_KEYWORD_LENGTH } from "../../../core/apiLimits.js";
import { icon } from "../icon/icon.js";

// Champ du header ; celui de l'accueil a un autre id (renderSearchBox({ id })).
const INPUT_ID = "site-search";
const INPUT_CLASS = "site-search__input";

/** Formulaire GET vers l'accueil : sans JS ou depuis une autre page, la recherche arrive en `?q=`. */
export function renderSearchBox({ id = INPUT_ID } = {}) {
  return h("form", {
    class: "site-search",
    role: "search",
    action: routeUrl("/"),
    method: "get",
  },
    h("label", { class: "visually-hidden", for: id }, "Rechercher un événement, un lieu ou une catégorie"),
    icon("search", { className: "site-search__icon" }),
    h("input", {
      class: INPUT_CLASS,
      id,
      type: "search",
      name: "q",
      placeholder: "Rechercher un événement, un lieu…",
      maxlength: MAX_KEYWORD_LENGTH,
      autocomplete: "off",
    }),
  );
}

/** A construire apres boot() ; sans champ, tout est sans effet. Les champs du header et de l'accueil restent synchronises. */
export class SearchBox {
  constructor(root = document) {
    /** @type {HTMLInputElement[]} */
    this.inputs = [...root.querySelectorAll(`input.${INPUT_CLASS}`)].map((el) => /** @type {HTMLInputElement} */ (el));
  }

  setQuery(query) {
    for (const input of this.inputs) input.value = query;
  }

  /** Recherche a l'envoi (Entree), ou des que le champ est vide (croix du champ search) : pas un appel par frappe.
   * `signal` retire ces ecouteurs quand on quitte la carte (le header reste affiche).
   * @param {(query: string) => void} onQuery
   * @param {{ signal?: AbortSignal }} [options] */
  bindSubmit(onQuery, { signal } = {}) {
    for (const input of this.inputs) {
      input.form?.addEventListener("submit", (e) => {
        e.preventDefault();
        this.setQuery(input.value);
        onQuery(input.value);
      }, { signal });
      input.addEventListener("input", () => {
        if (input.value !== "") return;
        this.setQuery("");
        onQuery("");
      }, { signal });
    }
  }
}
