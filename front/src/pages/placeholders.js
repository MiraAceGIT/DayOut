import { PATHS } from "../core/routes.js";
import { PlaceholderController } from "../controller/PlaceholderController.js";

function placeholder(id, path, title) {
  let controller = null;
  return {
    id,
    path,
    title,
    template: "screen-placeholder",
    mainClass: "container",
    mount() {
      controller = new PlaceholderController();
      controller.start({ title });
    },
    unmount() {
      controller = null;
    },
  };
}

export const placeholders = [
  placeholder("planning", PATHS.planning, "Mon planning"),
  placeholder("faq", PATHS.faq, "FAQ"),
  placeholder("terms", PATHS.terms, "Conditions générales d'utilisation"),
  placeholder("legal", PATHS.legal, "Mentions légales"),
  placeholder("contact", PATHS.contact, "Contact"),
  placeholder("login", PATHS.login, "Connexion"),
];
