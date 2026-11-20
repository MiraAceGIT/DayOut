import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { MapSearch } from "../src/model/state/MapSearch.js";
import { excerptAround, splitMatches } from "../src/shared/highlight.js";
import { resultsMessage } from "../src/view/home/resultsMessage.js";
import { event } from "./fixtures.js";

const NOW = new Date("2026-11-04T12:00:00Z");

describe("splitMatches", () => {
  it("surligne sans tenir compte de la casse ni des accents, en gardant le texte d'origine", () => {
    assert.deepEqual(splitMatches("Soirée JAZZ au Café", "jazz"), [
      { text: "Soirée ", match: false },
      { text: "JAZZ", match: true },
      { text: " au Café", match: false },
    ]);
    assert.deepEqual(splitMatches("Soirée", "SOIREE"), [{ text: "Soirée", match: true }]);
    assert.deepEqual(splitMatches("Café café", "cafe").map((s) => s.match), [true, false, true]);
  });

  it("garde les caracteres speciaux comme du texte et ne plante pas sur un motif regex", () => {
    assert.deepEqual(splitMatches("a <script> b", "<script>").map((s) => s.text), ["a ", "<script>", " b"]);
    assert.deepEqual(splitMatches("100% (fun)", "(fun"), [{ text: "100% ", match: false }, { text: "(fun", match: true }, { text: ")", match: false }]);
    assert.deepEqual(splitMatches("l'atelier", "'"), [
      { text: "l", match: false }, { text: "'", match: true }, { text: "atelier", match: false },
    ]);
  });

  it("renvoie le texte tel quel sans mot-cle, sans correspondance ou texte vide", () => {
    assert.deepEqual(splitMatches("Jazz", ""), [{ text: "Jazz", match: false }]);
    assert.deepEqual(splitMatches("Jazz", "rock"), [{ text: "Jazz", match: false }]);
    assert.deepEqual(splitMatches(null, "jazz"), []);
  });
});

describe("excerptAround", () => {
  it("centre l'extrait sur la premiere occurrence et marque les coupures", () => {
    const text = `${"a".repeat(100)} jazz ${"b".repeat(100)}`;
    const excerpt = excerptAround(text, "JAZZ", { before: 10, length: 30 });
    assert.ok(excerpt.startsWith("…") && excerpt.endsWith("…"));
    assert.ok(excerpt.includes("jazz"));
    assert.ok(excerpt.length <= 32);
  });

  it("garde le texte entier s'il est court et renvoie null sans occurrence", () => {
    assert.equal(excerptAround("Du jazz", "jazz"), "Du jazz");
    assert.equal(excerptAround("Du rock", "jazz"), null);
    assert.equal(excerptAround("Du jazz", ""), null);
  });
});

describe("resultsMessage avec mot-cle", () => {
  const base = { items: [], inCircle: 0, withoutPosition: 0, loadedCap: 0 };

  it("compte les resultats", () => {
    const items = [{}, {}];
    assert.equal(resultsMessage({ ...base, items, inCircle: 2, keyword: "jazz", total: 2 }), "2 résultats pour « jazz » dans ce cercle");
    assert.equal(resultsMessage({ ...base, items: [{}], inCircle: 1, keyword: "jazz", total: 4 }), "1 résultat pour « jazz » dans ce cercle · 4 au total");
    assert.doesNotMatch(
      resultsMessage({ ...base, items: [{}], inCircle: 1, keyword: "jazz", total: 100, loadedCap: 100 }),
      /au total/,
      "le total plafonne par la limite de chargement n'est pas un vrai total",
    );
  });

  it("propose d'elargir la recherche quand l'API ne renvoie rien", () => {
    const message = resultsMessage({ ...base, keyword: "zzz", total: 0 });
    assert.match(message, /^Aucun résultat pour « zzz »\. Élargis ta recherche/);
  });

  it("propose d'elargir le rayon quand les resultats sont hors cercle", () => {
    const message = resultsMessage({ ...base, keyword: "jazz", total: 3 });
    assert.match(message, /Aucun résultat pour « jazz » dans ce cercle · 3 au total\. Élargis le rayon/);
  });

  it("garde le message habituel sans mot-cle", () => {
    assert.equal(resultsMessage({ ...base, items: [{}], inCircle: 1, total: 1 }), "1 événement dans ce cercle");
    assert.match(resultsMessage(base), /^Aucun événement dans ce cercle\. Élargis le rayon/);
  });
});

describe("MapSearch avec mot-cle", () => {
  const future = (id, extra = {}) => event({
    id, start_at: "2026-11-10T20:00:00Z", end_at: "2026-11-10T23:00:00Z", ...extra,
  });

  it("n'applique pas de second filtre texte : les resultats de l'API sont gardes (description, categorie)", () => {
    const state = new MapSearch();
    const events = [future(1, { title: "Soirée", description: "Du jazz toute la nuit" })];
    state.load(events, { limit: 100, keyword: "jazz" });
    const result = state.results({ now: NOW });
    assert.deepEqual(result.items.map((i) => i.event.id), [1]);
    assert.equal(result.keyword, "jazz");
    assert.equal(result.total, 1);
  });

  it("retire les categories actives absentes des nouveaux resultats", () => {
    const state = new MapSearch();
    state.toggleCategory("sport", true);
    state.load([future(1)], { limit: 100, keyword: "jazz" });
    assert.equal(state.criteria.categories.has("sport"), false);
  });
});
