import { isApiError } from "../../core/errors.js";
import { passwordStrength, schoolForEmail, validateRegistration } from "../../model/domain/registration.js";
import { fetchCategories } from "../../model/services/categoryService.js";
import { fetchSchools } from "../../model/services/schoolService.js";
import { registerUser, rejectedRegistrationFields, retryAfterSeconds } from "../../model/services/userService.js";
import { RegisterView } from "../../view/user/RegisterView.js";
import { PageLoader } from "../PageLoader.js";

/** @typedef {import("../../model/mappers/schoolMapper.js").School} School */
/** @typedef {import("../../model/domain/registration.js").RegistrationValues} RegistrationValues */

export class RegisterController {
  /** @param {{ view?: Pick<RegisterView, "isReady" | "bind" | "readValues" | "showLoading" | "endLoading" | "showError"
   *   | "showOptions" | "showFieldErrors" | "showPasswordStrength" | "showCreatorSchool" | "selectSchool" | "showSubmitting"
   *   | "endSubmitting" | "showSubmitError" | "showRateLimited" | "showSuccess">,
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
      onInput: (field) => {
        if (field === "email") this.selectSchoolFromEmail();
        this.refresh();
      },
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
   * L'adresse fait foi : des que son domaine est celui d'une ecole, cette ecole est choisie dans la liste.
   * Un domaine inconnu ne change rien (l'erreur « aucune ecole partenaire » s'en charge).
   */
  selectSchoolFromEmail() {
    const values = this.view.readValues();
    const school = schoolForEmail(this.schools, values.email);
    if (school && String(school.id) !== values.schoolId) this.view.selectSchool(school.id);
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
        // Compte confirme, en attente, ou inscription simultanee : meme code, l'etat du compte ne se devine pas.
        if (error.code === "EMAIL_TAKEN") {
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
        if (error.status === 429) {
          this.view.showRateLimited(retryAfterSeconds(error));
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
