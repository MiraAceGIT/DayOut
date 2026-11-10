import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { MAX_KEYWORD_LENGTH } from "../../../model/services/eventService.js";
import { icon } from "../icon/icon.js";

// Id du champ du header ; l'accueil en a un second (`id` de renderSearchBox) : les autres views passent par SearchBox.
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
    this.inputs = [...root.querySelectorAll(`input.${INPUT_CLASS}`)];
  }

  setQuery(query) {
    for (const input of this.inputs) input.value = query;
  }

  /** Recherche a l'envoi (Entree) : un appel API par recherche, pas un par frappe.
   * Le header reste affiche : `signal` retire ces ecouteurs quand on quitte la carte. */
  bindSubmit(onQuery, { signal } = {}) {
    for (const input of this.inputs) {
      input.form?.addEventListener("submit", (e) => {
        e.preventDefault();
        this.setQuery(input.value);
        onQuery(input.value);
      }, { signal });
    }
  }
}
