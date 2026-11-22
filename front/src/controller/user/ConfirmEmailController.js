import { isApiError } from "../../core/errors.js";
import { confirmEmail } from "../../model/services/userService.js";
import { ConfirmEmailView } from "../../view/user/ConfirmEmailView.js";
import { PageLoader } from "../PageLoader.js";

/** Code d'erreur de POST /email-verifications -> issue affichee. */
const OUTCOME_BY_CODE = /** @type {const} */ ({
  VERIFICATION_TOKEN_INVALID: "invalid",
  VALIDATION_ERROR: "invalid",
  VERIFICATION_TOKEN_EXPIRED: "expired",
  VERIFICATION_TOKEN_USED: "used",
});

export class ConfirmEmailController {
  /** @param {{ view?: Pick<ConfirmEmailView, "isReady" | "bind" | "showLoading" | "endLoading" | "showError" | "showOutcome">,
   * confirm?: typeof confirmEmail }} [options] */
  constructor({ view = new ConfirmEmailView(), confirm = confirmEmail } = {}) {
    this.view = view;
    this.confirm = confirm;
    this.loader = new PageLoader(view);
    /** @type {string|null} */
    this.token = null;
    this.started = false;
  }

  /** @param {{ token?: string|null, signal?: AbortSignal }} [options] */
  async start({ token = null, signal } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.loader = new PageLoader(this.view, { signal });
    this.token = token || null;
    this.view.bind({ onRetry: () => this.load() }, signal);
    if (this.token === null) {
      this.view.showOutcome("invalid");
      return;
    }
    await this.load();
  }

  async load() {
    const token = this.token;
    if (token === null) return;
    await this.loader.run((signal) => this.confirm(token, { signal }), {
      onSuccess: (result) => this.view.showOutcome("confirmed", { creatorPending: result.creatorPending }),
      onError: (error) => {
        const outcome = isApiError(error) && error.code ? OUTCOME_BY_CODE[/** @type {keyof typeof OUTCOME_BY_CODE} */ (error.code)] : undefined;
        if (!outcome) return false;
        this.view.showOutcome(outcome);
        return true;
      },
    });
  }
}
