import { FaqController } from "../controller/FaqController.js";

/** @param {{ signal: AbortSignal }} options */
export function mountFaq({ signal }) {
  return new FaqController({ signal }).start();
}
