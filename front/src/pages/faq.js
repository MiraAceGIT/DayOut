import { FaqController } from "../controller/FaqController.js";
import { FaqView } from "../view/faq/FaqView.js";

/** @param {import("../app/router.js").PageContext} page */
export function mountFaq({ root, signal }) {
  return new FaqController({ view: new FaqView(root) }).start({ signal });
}
