import assert from "node:assert/strict";
import { after, before, describe, it } from "node:test";
import { event } from "./fixtures.js";

// Le controleur importe la configuration navigateur ; vue, carte et champ de recherche sont injectes.
// Import par variable : tsc (jsconfig.json) ne verifie pas encore la couche view/ que le controleur tire avec lui.
const homeControllerPath = "../src/controller/HomeController.js";
globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { HomeController } = await import(homeControllerPath);
const { ApiError } = await import("../src/core/api.js");
delete globalThis.window;

/** Doubles de test : seules les methodes appelees par le controleur existent. */
/** @returns {{ view: any, map: any, searchBox: any }} */
function stubs() {
  const view = {
    isReady: true, error: null, loading: false,
    bind() {}, setOrigin() {}, setRadius() {}, renderCategoryFilters() {}, showResults() {}, showSuggestions() {},
    showLoading() { this.loading = true; this.error = null; },
    endLoading() { this.loading = false; },
    showError(message) { this.error = message; },
  };
  const map = { init() {}, destroy() {}, renderLegend() {}, showEvents() {} };
  const searchBox = { queries: [], setQuery(q) { this.queries.push(q); }, bindSubmit(onQuery) { this.submit = onQuery; } };
  return { view, map, searchBox };
}

describe("HomeController : recherche par mot-cle", () => {
  // search() met a jour l'adresse (?q=) via location / history.
  before(() => {
    globalThis.location = /** @type {any} */ ({ href: "http://localhost/" });
    globalThis.history = /** @type {any} */ ({ state: null, replaceState() {} });
  });
  after(() => {
    delete globalThis.location;
    delete globalThis.history;
  });

  it("n'affiche pas d'erreur quand on quitte la carte pendant le chargement", async () => {
    const { view, map, searchBox } = stubs();
    const page = new AbortController();
    const loadEvents = ({ signal }) => new Promise((_, reject) => {
      signal.addEventListener("abort", () => reject(new ApiError("Requete annulee", { type: "abort" })));
    });
    const controller = new HomeController({ view, map, searchBox, loadEvents });

    const started = controller.start({ signal: page.signal });
    page.abort();
    await started;

    assert.equal(view.error, null);
    assert.equal(controller.state.status, "loading", "l'etat n'est pas passe en erreur");
  });

  it("transmet le mot-cle a l'API, ignore une recherche identique et vide la recherche", async () => {
    const { view, map, searchBox } = stubs();
    const calls = [];
    const loadEvents = async (options) => { calls.push(options.query); return [event({ id: 1 })]; };
    const controller = new HomeController({ view, map, searchBox, loadEvents });
    await controller.start({ query: "  jazz " });
    await searchBox.submit("jazz");
    await searchBox.submit("rock");
    await searchBox.submit("");
    assert.deepEqual(calls, ["jazz", "rock", ""]);
  });

  it("ne garde que le resultat de la recherche la plus recente", async () => {
    const { view, map, searchBox } = stubs();
    const resolvers = [];
    const loadEvents = ({ signal }) => new Promise((resolve, reject) => {
      signal.addEventListener("abort", () => reject(new ApiError("Requete annulee", { type: "abort" })));
      resolvers.push(resolve);
    });
    const controller = new HomeController({ view, map, searchBox, loadEvents });

    const first = controller.start({ query: "a" });
    const second = controller.search("b");
    resolvers[1]([event({ id: 2 })]);
    await Promise.all([first, second]);

    assert.equal(view.error, null);
    assert.deepEqual(controller.state.events.map((e) => e.id), [2]);
    assert.equal(controller.state.keyword, "b");
  });
});
