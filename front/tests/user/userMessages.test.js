import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { fieldMessage, fieldMessages, passwordStrengthLabel, successText } from "../../src/view/user/registerMessages.js";
import { confirmOutcome } from "../../src/view/user/confirmEmailMessages.js";

describe("inscription : textes", () => {
  it("a un message clair pour chaque code des regles", () => {
    assert.match(fieldMessage("email", "domain_not_allowed"), /aucune école partenaire/);
    assert.match(fieldMessage("email", "school_mismatch"), /ne correspond pas à l'école choisie/);
    assert.match(fieldMessage("password", "missing_digit"), /chiffre/);
    assert.deepEqual(fieldMessages({ termsAccepted: "required" }), { termsAccepted: "Tu dois accepter les conditions générales d'utilisation." });
  });

  it("retombe sur le refus du champ, puis sur un message generique", () => {
    assert.equal(fieldMessage("schoolId", "inconnu"), fieldMessage("schoolId", "rejected"));
    assert.equal(fieldMessage("searchRadiusKm", "rejected"), "Vérifie ce champ.");
    assert.equal(fieldMessage("champInconnu", "x"), "Vérifie ce champ.");
  });

  it("nomme le niveau de la jauge et rappelle l'adresse au succes", () => {
    assert.equal(passwordStrengthLabel(0), "");
    assert.equal(passwordStrengthLabel(4), "Force : excellente");
    assert.match(successText("a@isep.fr"), /a@isep\.fr.*24 h/);
  });
});

describe("confirmation : textes", () => {
  it("ajoute la demande createur en attente seulement apres une confirmation", () => {
    assert.match(confirmOutcome("confirmed", { creatorPending: true }).text, /administrateur/);
    assert.doesNotMatch(confirmOutcome("confirmed").text, /administrateur/);
    assert.doesNotMatch(confirmOutcome("expired", { creatorPending: true }).text, /administrateur/);
    assert.equal(confirmOutcome("used").title, "Lien déjà utilisé");
  });
});
