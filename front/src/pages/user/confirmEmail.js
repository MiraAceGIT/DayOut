import { ConfirmEmailController } from "../../controller/user/ConfirmEmailController.js";
import { ConfirmEmailView } from "../../view/user/ConfirmEmailView.js";

/** @param {import("../../app/router.js").PageContext} page */
export function mountConfirmEmail({ root, signal, params }) {
  const tokens = params.getAll("token");
  return new ConfirmEmailController({ view: new ConfirmEmailView(root) })
    .start({ token: tokens.length === 1 ? tokens[0] : null, signal });
}
