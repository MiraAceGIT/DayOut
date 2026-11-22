import { isApiError } from "../../core/errors.js";
import { passwordStrength, validateRegistration } from "../../model/domain/registration.js";
import { fetchCategories } from "../../model/services/categoryService.js";
import { fetchSchools } from "../../model/services/schoolService.js";
import { registerUser, rejectedRegistrationFields } from "../../model/services/userService.js";
import { RegisterView } from "../../view/user/RegisterView.js";
import { PageLoader } from "../PageLoader.js";

/** @typedef {import("../../model/mappers/schoolMapper.js").School} School */
/** @typedef {import("../../model/domain/registration.js").RegistrationValues} RegistrationValues */

export class RegisterController {
  /** @param {{ view?: Pick<RegisterView, "isReady" | "bind" | "readValues" | "showLoading" | "endLoading" | "showError"
   *   | "showOptions" | "showFieldErrors" | "showPasswordStrength" | "showCreatorSchool" | "showSubmitting"
   *   | "endSubmitting" | "showSubmitError" | "showSuccess">,
   * loadSchools?: typeof fetchSchools, loadCategories?: typeof fetchCategories, register?: typeof registerUser }} [options] */
  constructor({ view = new RegisterView(), loadSchools = fetchSchools, loadCategories = fetchCategories, register = registerUser } = {}) {
    this.view = view;
    this.loadSchools = loadSchools;
    this.loadCategories = loadCategories;
    this.register = register;
    this.loader = new PageLoader(view);
    this.sender = this.senderFor(view);
    /** @type {School[]} */
    this.schools = [];
    /** Champs deja quittes une fois : leurs erreurs s'affichent en direct. @type {Set<string>} */
    this.touched = new Set();
    this.submitted = false;
    this.started = false;
  }

  /** @param {{ signal?: AbortSignal }} [options] */
  async start({ signal } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.loader = new PageLoader(this.view, { signal });
    this.sender = this.senderFor(this.view, signal);
    this.view.bind({
      onInput: () => this.refresh(),
      onBlur: (field) => {
        this.touched.add(field);
        this.refresh();
      },
      onSubmit: () => this.submit(),
      onRetry: () => this.load(),
    }, signal);
    await this.load();
  }

  async load() {
    await this.loader.run(async (signal) => Promise.all([this.loadSchools({ signal }), this.loadCategories({ signal })]), {
      onSuccess: ([schools, categories]) => {
        this.schools = schools;
        this.view.showOptions(schools, categories);
      },
    });
  }

  /**
   * Validation en direct a chaque saisie : jauge, ecole de rattachement, erreurs des champs deja quittes
   * (tous apres un premier envoi). Tout est revalide : changer d'ecole met a jour l'erreur de l'e-mail.
   */
  refresh() {
    const values = this.view.readValues();
    this.view.showPasswordStrength(passwordStrength(values.password));
    this.view.showCreatorSchool(this.schools.find((school) => String(school.id) === values.schoolId)?.name ?? null);
    const errors = validateRegistration(values, this.schools);
    this.view.showFieldErrors(this.submitted ? errors : this.visible(errors));
  }

  async submit() {
    if (this.sender.busy) return;
    const values = this.view.readValues();
    this.submitted = true;
    const errors = validateRegistration(values, this.schools);
    if (Object.keys(errors).length > 0) {
      this.view.showFieldErrors(errors, { focus: true });
      return;
    }
    this.view.showFieldErrors({}, { focus: true });
    await this.sender.run((signal) => this.register(values, { signal }), {
      onSuccess: (user) => this.view.showSuccess({
        email: user.email,
        creatorPending: user.creatorStatus === "pending",
        demoUrl: user.demoConfirmationUrl,
      }),
      onError: (error) => {
        if (!isApiError(error)) return false;
        // EMAIL_TAKEN, ou CONFLICT (cle unique de l'e-mail) si deux inscriptions arrivent en meme temps.
        if (error.status === 409 && (error.code === "EMAIL_TAKEN" || error.code === "CONFLICT")) {
          this.view.showFieldErrors({ email: "taken" }, { focus: true });
          return true;
        }
        // Trigger de la base : l'ecole a ete desactivee pendant la saisie.
        if (error.code === "EMAIL_DOMAIN_NOT_ALLOWED") {
          this.view.showFieldErrors({ email: "domain_not_allowed" }, { focus: true });
          return true;
        }
        if (error.isValidation) {
          const fields = rejectedRegistrationFields(error);
          this.view.showFieldErrors(Object.fromEntries(fields.map((field) => [field, "rejected"])), { focus: true });
          return true;
        }
        return false;
      },
    });
  }

  /** @param {Record<string, string>} errors */
  visible(errors) {
    return Object.fromEntries(Object.entries(errors).filter(([field]) => this.touched.has(field)));
  }

  /**
   * Envoi du formulaire par PageLoader (un a la fois, message d'erreur, rien apres le depart de la page).
   * @param {Pick<RegisterView, "showSubmitting" | "endSubmitting" | "showSubmitError">} view
   * @param {AbortSignal} [signal]
   */
  senderFor(view, signal) {
    return new PageLoader({
      showLoading: () => view.showSubmitting(),
      endLoading: () => view.endSubmitting(),
      showError: (message) => view.showSubmitError(message),
    }, { signal });
  }
}
