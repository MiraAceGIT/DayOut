import { RegisterController } from "../../controller/user/RegisterController.js";
import { RegisterView } from "../../view/user/RegisterView.js";

/** @param {import("../../app/router.js").PageContext} page */
export function mountRegister({ root, signal }) {
  return new RegisterController({ view: new RegisterView(root) }).start({ signal });
}
