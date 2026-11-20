import { FaqController } from "../controller/FaqController.js";

/** FAQ (route `/faq`, voir app/appRoutes.js).
 * @param {{ signal: AbortSignal }} page */
export function mountFaq({ signal }) {
  return new FaqController().start({ signal });
}
