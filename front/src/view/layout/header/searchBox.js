import { h } from "../../../shared/dom.js";
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

/** A construire apres boot() ; sans champ, tout est sans effet. */
export class SearchBox {
  constructor(root = document) {
    this.inputEl = root.querySelector(`#${INPUT_ID}`);
  }

  setQuery(query) {
    if (this.inputEl) this.inputEl.value = query;
  }

  /** Le champ survit aux changements d'ecran : renvoie la fonction qui retire les ecouteurs. */
  bindLive(onQuery) {
    const input = this.inputEl;
    if (!input) return () => {};
    const onInput = () => onQuery(input.value);
    const onSubmit = (e) => {
      e.preventDefault();
      onQuery(input.value);
    };
    input.addEventListener("input", onInput);
    input.form?.addEventListener("submit", onSubmit);
    return () => {
      input.removeEventListener("input", onInput);
      input.form?.removeEventListener("submit", onSubmit);
    };
  }
}
