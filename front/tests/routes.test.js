import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { PATHS, loginUrl, parseHash, routeHref } from "../src/core/routes.js";

describe("routes", () => {
  it("parseHash : vide -> accueil, chemin et parametres separes", () => {
    assert.equal(parseHash("").path, PATHS.home);
    assert.equal(parseHash("#").path, PATHS.home);
    assert.equal(parseHash("#/").path, PATHS.home);
    const route = parseHash("#/connexion?next=%2Fevenements");
    assert.equal(route.path, PATHS.login);
    assert.equal(route.params.get("next"), "/evenements");
  });

  it("parseHash : un hash qui n'est pas en #/ n'est pas une route (skip-link)", () => {
    assert.equal(parseHash("#main"), null);
  });

  it("parseHash : slash final ignore, chemin inconnu conserve (404 decidee par le routeur)", () => {
    assert.equal(parseHash("#/evenements/").path, PATHS.events);
    assert.equal(parseHash("#/xyz").path, "/xyz");
  });

  it("routeHref et loginUrl produisent des liens #/...", () => {
    assert.equal(routeHref(PATHS.events), "#/evenements");
    assert.equal(routeHref(PATHS.home), "#/");
    assert.equal(loginUrl("/evenements"), "#/connexion?next=%2Fevenements");
    assert.deepEqual(parseHash(loginUrl("/planning")).params.get("next"), "/planning");
  });
});
