import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { suggestEvents } from "../src/model/domain/eventSuggestions.js";
import { MapSearch } from "../src/model/state/MapSearch.js";
import { event } from "./fixtures.js";

const NOW = new Date("2026-11-08T12:00:00Z");
const future = (id, day, overrides = {}) => event({ id,
  start_at: `2026-11-${day}T18:00:00Z`, end_at: `2026-11-${day}T23:00:00Z`, ...overrides });

describe("suggestions publiques", () => {
  it("retient trois evenements a venir avec des places, tries par date", () => {
    const events = [future(1, "12"), future(2, "10"), future(3, "11"), future(4, "13"),
      future(5, "07"), future(6, "09", { capacity: 2, registered_count: 2 })];
    assert.deepEqual(suggestEvents(events, { now: NOW }).map(item => item.id), [2, 3, 1]);
    assert.deepEqual(events.map(item => item.id), [1, 2, 3, 4, 5, 6], "le tableau charge n'est pas modifie");
  });

  it("garde les capacites illimitees et les evenements en cours", () => {
    const events = [future(1, "09", { capacity: null }), event({ id: 2, start_at: "2026-11-08T10:00:00Z", end_at: "2026-11-08T14:00:00Z" })];
    assert.deepEqual(suggestEvents(events, { now: NOW }).map(item => item.id), [2, 1]);
    assert.deepEqual(suggestEvents([], { now: NOW }), []);
    assert.deepEqual(suggestEvents(events, { now: NOW, limit: 0 }), []);
  });

  it("reutilise les memes objets que la liste et la recherche sans appliquer de personnalisation", () => {
    const state = new MapSearch();
    const events = [future(1, "09"), future(2, "10")];
    state.load(events, { limit: 100 });
    state.setQuery("aucun resultat");
    assert.deepEqual(state.results({ now: NOW }).items, []);
    const suggestions = state.suggestions({ now: NOW });
    assert.equal(suggestions[0], events[0]);
    assert.equal(suggestions[1], events[1]);
    assert.deepEqual(state.events, events);
  });
});
