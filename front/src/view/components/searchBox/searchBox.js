import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { MAX_KEYWORD_LENGTH } from "../../../core/apiLimits.js";
import { icon } from "../icon/icon.js";

const INPUT_ID = "site-search";
const INPUT_CLASS = "site-search__input";

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

// Champs du header et de l'accueil, relus a chaque appel : l'ordre de creation est indifferent.
export class SearchBox {
  /** @param {ParentNode} [root] tout le document : le champ du header est hors du <main> */
  constructor(root = document) {
    this.root = root;
  }

  /** @returns {HTMLInputElement[]} */
  get inputs() {
    return [...this.root.querySelectorAll(`input.${INPUT_CLASS}`)].map((el) => /** @type {HTMLInputElement} */ (el));
  }

  setQuery(query) {
    for (const input of this.inputs) input.value = query;
  }

  /**
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
