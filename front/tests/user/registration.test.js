import assert from "node:assert/strict";
import { describe, it } from "node:test";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const {
  emailDomain, passwordIssue, passwordStrength, schoolForEmail, validateRegistration, DEFAULT_RADIUS_KM,
} = await import("../../src/model/domain/registration.js");
const { toFormFields, toUserCreateDto, toEmailVerificationCreateDto } = await import("../../src/model/mappers/user/userRequestMapper.js");
const { toRegisteredUser, toEmailVerification } = await import("../../src/model/mappers/user/userMapper.js");
const { toSchool } = await import("../../src/model/mappers/schoolMapper.js");
const { registerUser, confirmEmail, rejectedRegistrationFields } = await import("../../src/model/services/userService.js");
const { fetchSchools } = await import("../../src/model/services/schoolService.js");
const { fetchCategories } = await import("../../src/model/services/categoryService.js");
const { ApiError } = await import("../../src/core/api.js");
delete globalThis.window;

const schools = [
  { id: 1, name: "ISEP", shortName: "ISEP", emailDomain: "isep.fr", city: "Issy" },
  { id: 2, name: "EPITA", shortName: "EPITA", emailDomain: "epita.fr", city: null },
];

/** @returns {import("../../src/model/domain/registration.js").RegistrationValues} */
function values(override = {}) {
  return {
    profile: "participant", firstName: "Lina", lastName: "Martin", email: "lina.martin@isep.fr", schoolId: "1",
    password: "Soiree2026", passwordConfirm: "Soiree2026", termsAccepted: true, interests: ["soiree"],
    searchRadiusKm: DEFAULT_RADIUS_KM, organizationName: "", individual: false, website: "", ...override,
  };
}

describe("inscription : regles du formulaire", () => {
  it("accepte un formulaire complet", () => {
    assert.deepEqual(validateRegistration(values(), schools), {});
    assert.deepEqual(validateRegistration(values({ profile: "creator", organizationName: "BDE ISEP" }), schools), {});
    assert.deepEqual(validateRegistration(values({ profile: "creator", individual: true }), schools), {});
  });

  it("verifie le domaine de l'e-mail et sa coherence avec l'ecole choisie", () => {
    assert.equal(emailDomain("  Lina@ISEP.FR "), "isep.fr");
    assert.equal(emailDomain("sans-arobase"), "");
    assert.equal(schoolForEmail(schools, "a@epita.fr")?.id, 2);
    assert.equal(schoolForEmail(schools, "a@gmail.com"), null);
    assert.equal(validateRegistration(values({ email: "lina@gmail.com" }), schools).email, "domain_not_allowed");
    assert.equal(validateRegistration(values({ email: "lina@epita.fr" }), schools).email, "school_mismatch");
    assert.equal(validateRegistration(values({ email: "lina@isep" }), schools).email, "invalid");
    assert.equal(validateRegistration(values({ email: " " }), schools).email, "required");
    assert.deepEqual(validateRegistration(values({ schoolId: "" }), schools), { schoolId: "required" }, "domain alone is not enough");
    assert.equal(validateRegistration(values({ schoolId: "99" }), schools).schoolId, "required");
  });

  it("exige 8 caracteres, une majuscule et un chiffre, et une confirmation identique", () => {
    assert.equal(passwordIssue(""), "required");
    assert.equal(passwordIssue("Court1"), "too_short");
    assert.equal(passwordIssue("sansmajuscule1"), "missing_uppercase");
    assert.equal(passwordIssue("SansChiffre"), "missing_digit");
    assert.equal(passwordIssue("é".repeat(37) + "A1"), "too_long", "72 octets, comme bcrypt cote serveur");
    assert.equal(passwordIssue("Été2026xx"), null, "majuscule accentuee");
    assert.equal(validateRegistration(values({ passwordConfirm: "Soiree2027" }), schools).passwordConfirm, "mismatch");
    assert.equal(validateRegistration(values({ passwordConfirm: "" }), schools).passwordConfirm, "required");
  });

  it("donne une force de 0 a 4, faible des le premier caractere tant que les regles ne sont pas remplies", () => {
    assert.equal(passwordStrength(""), 0);
    assert.equal(passwordStrength("abc"), 1);
    assert.equal(passwordStrength("ABCDEFG1"), 1, "valide mais sans minuscule");
    assert.equal(passwordStrength("abcdefgh"), 1);
    assert.equal(passwordStrength("Abcdefg1"), 2);
    assert.equal(passwordStrength("Abcdefghijk1"), 3);
    assert.equal(passwordStrength("Abcdefghijk1!"), 4);
  });

  it("exige CGU, un centre d'interet, un rayon de 1 a 20 km et l'association d'un createur", () => {
    const errors = validateRegistration(values({
      termsAccepted: false, interests: [], searchRadiusKm: 21, profile: "creator", firstName: " ", lastName: "x".repeat(81),
    }), schools);
    assert.deepEqual(errors, {
      lastName: "too_long", firstName: "required", organizationName: "required", interests: "required",
      searchRadiusKm: "out_of_range", termsAccepted: "required",
    });
    assert.deepEqual(Object.keys(errors), ["lastName", "firstName", "organizationName", "interests", "searchRadiusKm", "termsAccepted"], "ordre du formulaire");
  });
});

describe("inscription : mappers", () => {
  it("construit le corps de POST /users sans la confirmation du mot de passe", () => {
    assert.deepEqual(toUserCreateDto(values({ email: " Lina.Martin@ISEP.fr ", interests: ["soiree", "soiree", "sport"] })), {
      profile: "participant", first_name: "Lina", last_name: "Martin", email: "lina.martin@isep.fr", school_id: 1,
      password: "Soiree2026", terms_accepted: true, interests: ["soiree", "sport"], search_radius_km: 3,
      organization_name: null, individual: false, website: "",
    });
    assert.equal(toUserCreateDto(values({ profile: "creator", organizationName: " BDE " })).organization_name, "BDE");
    const solo = toUserCreateDto(values({ profile: "creator", individual: true, organizationName: "Ignore" }));
    assert.deepEqual([solo.organization_name, solo.individual], [null, true]);
    assert.equal(toUserCreateDto(values({ individual: true })).individual, false, "participant : jamais individuel");
    assert.deepEqual(toEmailVerificationCreateDto("abc"), { token: "abc" });
  });

  it("lit les reponses de l'API", () => {
    assert.deepEqual(toRegisteredUser({ id: 7, email: "a@isep.fr", first_name: "A", role: "participant", creator_status: "pending",
      email_verified: false, demo_confirmation_url: null }), { id: 7, email: "a@isep.fr", firstName: "A", role: "participant",
      creatorStatus: "pending", emailVerified: false, demoConfirmationUrl: null });
    assert.deepEqual(toEmailVerification({ email_verified: true, creator_status: "pending" }), { emailVerified: true, creatorPending: true });
    assert.deepEqual(toSchool({ id: 1, name: "ISEP", short_name: null, email_domain: "ISEP.fr", city: null }),
      { id: 1, name: "ISEP", shortName: null, emailDomain: "isep.fr", city: null });
  });

  it("renomme les champs refuses par le serveur comme le formulaire", () => {
    assert.deepEqual(toFormFields({ first_name: "x", school_id: "y", terms_accepted: "z", inconnu: "w" }),
      ["firstName", "schoolId", "termsAccepted", "inconnu"]);
    assert.deepEqual(toFormFields(null), []);
    const error = new ApiError("Invalid input", { status: 422, code: "VALIDATION_ERROR", details: { email: "Bad" } });
    assert.deepEqual(rejectedRegistrationFields(error), ["email"]);
  });
});

describe("inscription : API", () => {
  it("envoie l'inscription en JSON et lit le compte cree", async (t) => {
    t.mock.method(globalThis, "fetch", async (url, options) => {
      assert.equal(new URL(url).pathname, "/users");
      assert.equal(options.method, "POST");
      assert.equal(options.headers["Content-Type"], "application/json");
      const body = JSON.parse(options.body);
      assert.equal(body.password, "Soiree2026");
      assert.equal("passwordConfirm" in body || "password_confirm" in body, false);
      return Response.json({ data: { id: 3, email: body.email, first_name: "Lina", role: "participant", creator_status: "none",
        email_verified: false, demo_confirmation_url: "http://localhost:5500/confirm-email?token=abc" } }, { status: 201 });
    });
    const user = await registerUser(values());
    assert.equal(user.email, "lina.martin@isep.fr");
    assert.equal(user.demoConfirmationUrl, "http://localhost:5500/confirm-email?token=abc");
  });

  it("transmet les refus du serveur en ApiError", async (t) => {
    t.mock.method(globalThis, "fetch", async () => Response.json(
      { error: { code: "EMAIL_TAKEN", message: "Taken", details: { field: "email" } } }, { status: 409 }));
    await assert.rejects(registerUser(values()), (/** @type {any} */ err) => err.name === "ApiError" && err.status === 409 && err.code === "EMAIL_TAKEN");
  });

  it("confirme l'adresse par POST avec le jeton", async (t) => {
    t.mock.method(globalThis, "fetch", async (url, options) => {
      assert.equal(new URL(url).pathname, "/email-verifications");
      assert.equal(options.method, "POST");
      assert.deepEqual(JSON.parse(options.body), { token: "a".repeat(64) });
      return Response.json({ data: { email_verified: true, creator_status: "none" } });
    });
    assert.deepEqual(await confirmEmail("a".repeat(64)), { emailVerified: true, creatorPending: false });
  });

  it("charge les ecoles et les categories", async (t) => {
    t.mock.method(globalThis, "fetch", async (url) => {
      const path = new URL(url).pathname;
      if (path === "/schools") return Response.json({ data: [{ id: 1, name: "ISEP", short_name: "ISEP", email_domain: "isep.fr", city: null }] });
      assert.equal(path, "/categories");
      return Response.json({ data: [{ slug: "soiree", label: "Soirée" }] });
    });
    assert.deepEqual((await fetchSchools()).map((s) => s.emailDomain), ["isep.fr"]);
    assert.deepEqual(await fetchCategories(), [{ slug: "soiree", label: "Soirée" }]);
  });

  it("refuse une liste d'ecoles malformee", async (t) => {
    t.mock.method(globalThis, "fetch", async () => Response.json({ data: { items: [] } }));
    await assert.rejects(fetchSchools(), TypeError);
  });
});
