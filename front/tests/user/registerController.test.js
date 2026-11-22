import assert from "node:assert/strict";
import { describe, it } from "node:test";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { RegisterController } = await import("../../src/controller/user/RegisterController.js");
const { ConfirmEmailController } = await import("../../src/controller/user/ConfirmEmailController.js");
const { ApiError } = await import("../../src/core/api.js");
delete globalThis.window;

const schools = [{ id: 1, name: "ISEP", shortName: "ISEP", emailDomain: "isep.fr", city: null }];
const categories = [{ slug: "soiree", label: "Soirée" }];

/** @param {Partial<import("../../src/model/domain/registration.js").RegistrationValues>} [override]
 * @returns {import("../../src/model/domain/registration.js").RegistrationValues} */
function formValues(override = {}) {
  return {
    profile: "participant", firstName: "Lina", lastName: "Martin", email: "lina.martin@isep.fr", schoolId: "1",
    password: "Soiree2026", passwordConfirm: "Soiree2026", termsAccepted: true, interests: ["soiree"],
    searchRadiusKm: 3, organizationName: "", individual: false, ...override,
  };
}

function registerView(values = formValues()) {
  return {
    isReady: true, values, handlers: null, options: null, errors: null, focus: false, strength: null, creatorSchool: undefined,
    submitting: false, submitError: "", loadError: "", success: null,
    bind(handlers) { this.handlers = handlers; },
    readValues() { return this.values; },
    showLoading() {}, endLoading() {},
    showError(message) { this.loadError = message; },
    showOptions(s, c) { this.options = { schools: s, categories: c }; },
    showFieldErrors(errors, { focus = false } = {}) { this.errors = errors; this.focus = focus; },
    showPasswordStrength(level) { this.strength = level; },
    showCreatorSchool(name) { this.creatorSchool = name; },
    showSubmitting() { this.submitting = true; },
    endSubmitting() { this.submitting = false; },
    showSubmitError(message) { this.submitError = message; },
    showSuccess(result) { this.success = result; },
  };
}

/** @param {{ view?: ReturnType<typeof registerView>, register?: (values: any) => Promise<any> }} [options] */
async function started({ view = registerView(), register = async () => { throw new Error("not called"); } } = {}) {
  const controller = new RegisterController({
    view, register, loadSchools: async () => schools, loadCategories: async () => categories,
  });
  await controller.start();
  return { controller, view };
}

describe("inscription : controleur", () => {
  it("charge ecoles et categories puis affiche le formulaire", async () => {
    const { view } = await started();
    assert.deepEqual(view.options, { schools, categories });
  });

  it("n'affiche une erreur qu'apres avoir quitte le champ, puis en direct", async () => {
    const { view } = await started({ view: registerView(formValues({ email: "lina@gmail.com", password: "abc" })) });
    view.handlers.onInput("email");
    assert.deepEqual(view.errors, {}, "pas d'erreur pendant la premiere saisie");
    assert.equal(view.strength, 1);
    view.handlers.onBlur("email");
    assert.deepEqual(view.errors, { email: "domain_not_allowed" });
    view.handlers.onBlur("password");
    assert.deepEqual(view.errors, { email: "domain_not_allowed", password: "too_short" });
    assert.equal(view.creatorSchool, "ISEP");
  });

  it("bloque l'envoi d'un formulaire invalide, focus sur les erreurs", async () => {
    let calls = 0;
    const { view } = await started({ view: registerView(formValues({ termsAccepted: false })), register: async () => { calls++; return null; } });
    await view.handlers.onSubmit();
    assert.equal(calls, 0);
    assert.deepEqual(view.errors, { termsAccepted: "required" });
    assert.equal(view.focus, true);
  });

  it("envoie la saisie et affiche « verifie ta boite mail »", async () => {
    /** @type {any} */
    let sent = null;
    const register = async (values) => {
      sent = values;
      return { id: 1, email: "lina.martin@isep.fr", firstName: "Lina", role: "participant", creatorStatus: "pending",
        emailVerified: false, demoConfirmationUrl: null };
    };
    const { view } = await started({ register });
    await view.handlers.onSubmit();
    assert.equal(sent.email, "lina.martin@isep.fr");
    assert.deepEqual(view.success, { email: "lina.martin@isep.fr", creatorPending: true, demoUrl: null });
    assert.equal(view.submitting, false);
  });

  it("affiche un e-mail deja pris et les refus du serveur sous les champs, saisie conservee", async () => {
    let error = new ApiError("Taken", { status: 409, code: "EMAIL_TAKEN", details: { field: "email" } });
    const { view } = await started({ register: async () => { throw error; } });
    await view.handlers.onSubmit();
    assert.deepEqual(view.errors, { email: "taken" });
    assert.equal(view.success, null);

    error = new ApiError("Invalid input", { status: 422, code: "VALIDATION_ERROR", details: { school_id: "Unknown", interests: "Unknown" } });
    await view.handlers.onSubmit();
    assert.deepEqual(view.errors, { schoolId: "rejected", interests: "rejected" });
    assert.equal(view.submitError, "");

    error = new ApiError("Down", { type: "network" });
    await view.handlers.onSubmit();
    assert.match(view.submitError, /joindre le serveur/);
  });

  it("met aussi sous l'e-mail les refus rares : doublon simultane, ecole desactivee", async () => {
    let error = new ApiError("Resource already exists", { status: 409, code: "CONFLICT" });
    const { view } = await started({ register: async () => { throw error; } });
    await view.handlers.onSubmit();
    assert.deepEqual(view.errors, { email: "taken" });

    error = new ApiError("Email domain not allowed", { status: 422, code: "EMAIL_DOMAIN_NOT_ALLOWED" });
    await view.handlers.onSubmit();
    assert.deepEqual(view.errors, { email: "domain_not_allowed" });

    error = new ApiError("Link already sent", { status: 409, code: "EMAIL_CONFIRMATION_PENDING", details: { field: "email" } });
    await view.handlers.onSubmit();
    assert.deepEqual(view.errors, { email: "pending" });
  });
});

function confirmView() {
  return {
    isReady: true, outcome: null, options: null, error: "", retry: null,
    bind({ onRetry }) { this.retry = onRetry; },
    showLoading() {}, endLoading() {},
    showError(message) { this.error = message; },
    showOutcome(outcome, options) { this.outcome = outcome; this.options = options; },
  };
}

describe("confirmation de l'adresse : controleur", () => {
  it("confirme et signale une demande createur en attente", async () => {
    const view = confirmView();
    let token = null;
    /** @type {string[][]} */
    const params = [];
    await new ConfirmEmailController({ view, confirm: async (t) => { token = t; return { emailVerified: true, creatorPending: true }; } })
      .start({ token: "a".repeat(64), setParam: (name, value) => params.push([name, value]) });
    assert.equal(token, "a".repeat(64));
    assert.equal(view.outcome, "confirmed");
    assert.deepEqual(view.options, { creatorPending: true });
    assert.deepEqual(params, [["token", ""]], "le jeton quitte l'adresse");
  });

  it("refuse sans requete un lien sans jeton", async () => {
    const view = confirmView();
    await new ConfirmEmailController({ view, confirm: async () => assert.fail("no request") }).start({ token: null });
    assert.equal(view.outcome, "invalid");
  });

  /** @type {Array<[string, number, "expired" | "used" | "invalid"]>} */
  const cases = [
    ["VERIFICATION_TOKEN_EXPIRED", 422, "expired"],
    ["VERIFICATION_TOKEN_USED", 409, "used"],
    ["VERIFICATION_TOKEN_INVALID", 422, "invalid"],
  ];
  for (const [code, status, outcome] of cases) {
    it(`affiche « ${outcome} » pour ${code}`, async () => {
      const view = confirmView();
      await new ConfirmEmailController({ view, confirm: async () => { throw new ApiError("x", { status, code }); } }).start({ token: "b".repeat(64) });
      assert.equal(view.outcome, outcome);
    });
  }

  it("propose de reessayer apres une panne", async () => {
    const view = confirmView();
    let fail = true;
    /** @type {string[][]} */
    const params = [];
    await new ConfirmEmailController({ view, confirm: async () => {
      if (fail) throw new ApiError("Down", { type: "network" });
      return { emailVerified: true, creatorPending: false };
    } }).start({ token: "c".repeat(64), setParam: (name, value) => params.push([name, value]) });
    assert.equal(view.outcome, null);
    assert.match(view.error, /joindre le serveur/);
    assert.deepEqual(params, [], "panne : le jeton reste pour reessayer apres un rechargement");
    fail = false;
    await view.retry();
    assert.equal(view.outcome, "confirmed");
  });
});
