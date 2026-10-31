import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { isUpcoming } from "../src/model/domain/Event.js";
import { matchesCriteria, normalize, weekendRange } from "../src/model/domain/eventFilters.js";
import { searchEvents } from "../src/model/domain/eventSearch.js";
import { event } from "./fixtures.js";

const NOW = new Date("2026-11-04T12:00:00Z");
const ORIGIN = { lat: 48.8455, lng: 2.3275 }; // ISEP

describe("eventFilters", () => {
  it("normalize ignore accents, casse et espaces", () => {
    assert.equal(normalize("  Soirée ÉTÉ "), "soiree ete");
    assert.equal(normalize(null), "");
  });

  it("weekendRange couvre samedi 0 h -> lundi 0 h, le week-end en cours ou le prochain", () => {
    const at = (y, m, d) => new Date(y, m - 1, d, 15);
    const saturday = new Date(2026, 10, 7);
    const monday = new Date(2026, 10, 9);
    for (const now of [at(2026, 11, 4), at(2026, 11, 7), at(2026, 11, 8)]) { // mercredi, samedi, dimanche
      const { start, end } = weekendRange(now);
      assert.deepEqual([start, end], [saturday, monday], now.toDateString());
    }
  });

  it("matchesCriteria combine les filtres actifs en ET", () => {
    const free = event({ is_paid: false, title: "Concert gratuit" });
    const paid = event({ is_paid: true, title: "Gala" });
    const full = event({ is_paid: false, capacity: 5, registered_count: 5 });

    assert.equal(matchesCriteria({})(paid), true, "aucun filtre actif");
    assert.equal(matchesCriteria({ free: true })(paid), false);
    assert.equal(matchesCriteria({ free: true, query: "CONCERT" })(free), true);
    assert.equal(matchesCriteria({ free: true, available: true })(full), false);
    assert.equal(matchesCriteria({ categories: new Set(["sport"]) })(paid), false);
    assert.equal(matchesCriteria({ categories: new Set(["sport", "soiree"]) })(paid), true, "categories en OU");
  });
});

describe("isUpcoming", () => {
  it("exclut un evenement termine, se rabat sur le debut, garde une date illisible", () => {
    assert.equal(isUpcoming({ startAt: "2026-11-01T20:00:00Z", endAt: "2026-11-05T00:00:00Z" }, NOW), true);
    assert.equal(isUpcoming({ startAt: "2026-11-01T20:00:00Z", endAt: "2026-11-02T00:00:00Z" }, NOW), false);
    assert.equal(isUpcoming({ startAt: "2026-11-01T20:00:00Z", endAt: null }, NOW), false);
    assert.equal(isUpcoming({ startAt: "pas une date" }, NOW), true);
  });
});

describe("searchEvents", () => {
  const events = [
    event({ id: 1, start_at: "2026-11-10T20:00:00Z", end_at: "2026-11-10T23:00:00Z" }), // ~2,5 km
    event({ id: 2, start_at: "2026-11-06T20:00:00Z", end_at: "2026-11-06T23:00:00Z", latitude: null }),
    event({ id: 3, start_at: "2026-11-08T20:00:00Z", end_at: "2026-11-08T23:00:00Z", latitude: 48.7, longitude: 2.1 }), // ~23 km
    event({ id: 4, start_at: "2026-10-01T20:00:00Z", end_at: "2026-10-01T23:00:00Z" }), // passe
  ];

  it("garde l'a venir dans le rayon (et sans position), trie par date, compte le cercle", () => {
    const result = searchEvents(events, { origin: ORIGIN, radiusKm: 3, now: NOW });
    assert.deepEqual(result.items.map((i) => i.event.id), [2, 1]);
    assert.equal(result.inCircle, 1);
    assert.equal(result.withoutPosition, 1);
    assert.equal(result.items[0].distanceKm, null);
    assert.ok(result.items[1].distanceKm > 2 && result.items[1].distanceKm < 3);
  });

  it("un rayon plus grand inclut l'evenement lointain", () => {
    const ids = searchEvents(events, { origin: ORIGIN, radiusKm: 25, now: NOW }).items.map((i) => i.event.id);
    assert.deepEqual(ids, [2, 3, 1]);
  });
});
