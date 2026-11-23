import { h } from "../../shared/dom.js";
import { clearFieldErrors, showFieldErrors } from "../../shared/forms.js";
import {
  creatorSchoolText, DEMO_LINK_INTRO, DEMO_LINK_LABEL, fieldMessages, passwordStrengthLabel, radiusLabel,
  REGISTER_INVALID, REGISTER_LOADING, REGISTER_SUBMITTING, SUCCESS_CREATOR, successText,
} from "./registerMessages.js";

/** @typedef {import("../../model/domain/registration.js").RegistrationValues} RegistrationValues */
/** @typedef {{ id: number, name: string }} SchoolOption */
/** @typedef {{ slug: string, label: string }} CategoryOption */

export class RegisterView {
  /** @param {ParentNode} [root] */
  constructor(root = document) {
    this.status = /** @type {HTMLElement} */ (root.querySelector("#register-status"));
    this.error = /** @type {HTMLElement} */ (root.querySelector("#register-error"));
    this.retry = /** @type {HTMLButtonElement} */ (root.querySelector("#register-retry"));
    this.form = /** @type {HTMLFormElement} */ (root.querySelector("#register-form"));
    this.school = /** @type {HTMLSelectElement} */ (root.querySelector("#register-school"));
    this.interests = /** @type {HTMLElement} */ (root.querySelector("#register-interests"));
    this.creator = /** @type {HTMLElement} */ (root.querySelector("#register-creator"));
    this.creatorNotice = /** @type {HTMLElement} */ (root.querySelector("#register-creator-notice"));
    this.creatorSchool = /** @type {HTMLElement} */ (root.querySelector("#register-creator-school"));
    this.organization = /** @type {HTMLInputElement} */ (root.querySelector("#register-organization"));
    this.strengthBar = /** @type {HTMLElement} */ (root.querySelector(".password-strength__bar"));
    this.strengthLabel = /** @type {HTMLElement} */ (root.querySelector("#register-password-strength"));
    this.radius = /** @type {HTMLInputElement} */ (root.querySelector("#register-radius"));
    this.radiusValue = /** @type {HTMLOutputElement} */ (root.querySelector("#register-radius-value"));
    this.submitError = /** @type {HTMLElement} */ (root.querySelector("#register-submit-error"));
    this.submit = /** @type {HTMLButtonElement} */ (root.querySelector("#register-submit"));
    this.success = /** @type {HTMLElement} */ (root.querySelector("#register-success"));
    this.successText = /** @type {HTMLElement} */ (root.querySelector("#register-success-text"));
    this.successCreator = /** @type {HTMLElement} */ (root.querySelector("#register-success-creator"));
    this.successDemo = /** @type {HTMLElement} */ (root.querySelector("#register-success-demo"));
  }

  get isReady() {
    return Boolean(this.status && this.error && this.retry && this.form && this.school && this.interests && this.creator
      && this.creatorNotice && this.creatorSchool && this.organization && this.strengthBar && this.strengthLabel
      && this.radius && this.radiusValue && this.submitError && this.submit && this.success && this.successText
      && this.successCreator && this.successDemo);
  }

  /**
   * `field` : nom du champ (attribut name). onInput a chaque saisie, onBlur en quittant un champ.
   * @param {{ onInput: (field: string) => void, onBlur: (field: string) => void, onSubmit: () => void, onRetry: () => void }} handlers
   * @param {AbortSignal} [signal]
   */
  bind({ onInput, onBlur, onSubmit, onRetry }, signal) {
    const fieldOf = (/** @type {EventTarget|null} */ target) =>
      target instanceof HTMLInputElement || target instanceof HTMLSelectElement ? target.name : "";
    this.form.addEventListener("input", (e) => {
      this.syncDisplay();
      const field = fieldOf(e.target);
      if (field) onInput(field);
    }, { signal });
    this.form.addEventListener("change", (e) => {
      const field = fieldOf(e.target);
      if (field) onBlur(field); // cases, boutons radio et liste : le choix vaut sortie du champ
    }, { signal });
    this.form.addEventListener("focusout", (e) => {
      const field = fieldOf(e.target);
      if (field) onBlur(field);
    }, { signal });
    this.form.addEventListener("submit", (e) => {
      e.preventDefault();
      onSubmit();
    }, { signal });
    this.retry.addEventListener("click", onRetry, { signal });
  }

  /** @returns {RegistrationValues} */
  readValues() {
    const data = new FormData(this.form);
    const text = (/** @type {string} */ name) => String(data.get(name) ?? "");
    return {
      profile: text("profile") === "creator" ? "creator" : "participant",
      firstName: text("firstName"),
      lastName: text("lastName"),
      email: text("email"),
      schoolId: text("schoolId"),
      password: text("password"),
      passwordConfirm: text("passwordConfirm"),
      termsAccepted: data.has("termsAccepted"),
      interests: data.getAll("interests").map(String),
      searchRadiusKm: Number(text("searchRadiusKm")),
      organizationName: text("organizationName"),
      individual: data.has("individual"),
      website: text("website"),
    };
  }

  showLoading() {
    this.status.textContent = REGISTER_LOADING;
    this.error.textContent = "";
    this.retry.hidden = true;
  }

  endLoading() {}

  /** @param {string} message */
  showError(message) {
    this.status.textContent = "";
    this.error.textContent = message;
    this.retry.hidden = false;
  }

  /** @param {SchoolOption[]} schools @param {CategoryOption[]} categories */
  showOptions(schools, categories) {
    this.school.replaceChildren(
      /** @type {HTMLElement} */ (this.school.options[0]),
      ...schools.map((school) => h("option", { value: String(school.id) }, school.name)),
    );
    this.interests.replaceChildren(...categories.map((category) =>
      h("label", { class: "interest-list__item" },
        h("input", { type: "checkbox", name: "interests", value: category.slug }),
        h("span", {}, category.label))));
    this.status.textContent = "";
    this.form.hidden = false;
    this.syncDisplay();
  }

  /**
   * Erreurs a afficher (champ -> code), les autres sont effacees ; `focus` : premier champ en erreur (envoi).
   * @param {Record<string, string>} errors
   * @param {{ focus?: boolean }} [options]
   */
  showFieldErrors(errors, { focus = false } = {}) {
    showFieldErrors(this.form, fieldMessages(errors), { focus });
    const invalid = Object.keys(errors).length > 0;
    if (focus) this.submitError.textContent = invalid ? REGISTER_INVALID : "";
    else if (!invalid && this.submitError.textContent === REGISTER_INVALID) this.submitError.textContent = "";
  }

  /** @param {number} level 0 a 4 */
  showPasswordStrength(level) {
    this.strengthBar.dataset.level = String(level);
    this.strengthLabel.textContent = passwordStrengthLabel(level);
  }

  /** @param {string|null} schoolName ecole choisie, rattachement du createur */
  showCreatorSchool(schoolName) {
    this.creatorSchool.textContent = creatorSchoolText(schoolName);
  }

  showSubmitting() {
    this.form.setAttribute("aria-busy", "true");
    this.submit.disabled = true;
    this.submitError.textContent = "";
    this.status.textContent = REGISTER_SUBMITTING;
  }

  endSubmitting() {
    this.form.setAttribute("aria-busy", "false");
    this.submit.disabled = false;
    if (this.status.textContent === REGISTER_SUBMITTING) this.status.textContent = "";
  }

  /** @param {string} message la saisie reste en place */
  showSubmitError(message) {
    this.status.textContent = "";
    this.submitError.textContent = message;
  }

  /** @param {{ email: string, creatorPending: boolean, demoUrl: string|null }} result */
  showSuccess({ email, creatorPending, demoUrl }) {
    clearFieldErrors(this.form);
    this.form.reset();
    this.form.hidden = true;
    this.status.textContent = "";
    this.successText.textContent = successText(email);
    this.successCreator.hidden = !creatorPending;
    this.successCreator.textContent = creatorPending ? SUCCESS_CREATOR : "";
    this.successDemo.hidden = demoUrl === null;
    this.successDemo.replaceChildren(...(demoUrl === null ? [] : [DEMO_LINK_INTRO, h("a", { href: demoUrl }, DEMO_LINK_LABEL)]));
    this.success.hidden = false;
    this.success.focus();
  }

  // Affichage qui ne depend que du formulaire : bloc createur, nom d'association, valeur du rayon.
  syncDisplay() {
    const values = this.readValues();
    const creator = values.profile === "creator";
    this.creator.hidden = !creator;
    this.creatorNotice.hidden = !creator;
    this.organization.disabled = values.individual;
    this.showRadius(values.searchRadiusKm);
  }

  /** Le trait orange du curseur (style `.range` partage avec la carte) suit `--range-progress`. @param {number} km */
  showRadius(km) {
    const min = Number(this.radius.min);
    const max = Number(this.radius.max);
    this.radius.style.setProperty("--range-progress", `${((km - min) / (max - min)) * 100}%`);
    this.radius.setAttribute("aria-valuetext", radiusLabel(km));
    this.radiusValue.textContent = radiusLabel(km);
  }
}
