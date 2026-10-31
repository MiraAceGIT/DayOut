import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { toCategory, toCategoryDto } from "../src/model/mappers/categoryMapper.js";
import { toEvent, toEventDto, toEvents } from "../src/model/mappers/eventMapper.js";
import { eventDto } from "./fixtures.js";

describe("toEvent", () => {
  it("passe en camelCase et regroupe la position", () => {
    const event = toEvent(eventDto());
    assert.equal(event.startAt, "2026-11-07T20:00:00+00:00");
    assert.equal(event.venueName, "Le Rooftop");
    assert.deepEqual(event.position, { lat: 48.8689, lng: 2.341 });
    assert.deepEqual(event.categories, [{ slug: "soiree", label: "Soirée" }]);
    assert.equal("start_at" in event, false, "aucun nom du JSON ne fuit");
  });

  it("accepte des coordonnees en chaine, sans jamais prendre 0 pour une absence", () => {
    assert.deepEqual(toEvent(eventDto({ latitude: "48.85", longitude: "2.35" })).position, { lat: 48.85, lng: 2.35 });
    assert.deepEqual(toEvent(eventDto({ latitude: 0, longitude: 0 })).position, { lat: 0, lng: 0 });
    for (const latitude of [null, undefined, "", "abc"]) {
      assert.equal(toEvent(eventDto({ latitude })).position, null, String(latitude));
    }
  });

  it("calcule places restantes et disponibilite", () => {
    const cases = [
      [{ capacity: 250, registered_count: 12 }, 238, "open"],
      [{ capacity: 10, registered_count: 5 }, 5, "last"],
      [{ capacity: 10, registered_count: 10 }, 0, "full"],
      [{ capacity: 10, registered_count: 14 }, 0, "full"],
      [{ capacity: null, registered_count: 3 }, null, null],
    ];
    for (const [overrides, remaining, availability] of cases) {
      const event = toEvent(eventDto(overrides));
      assert.equal(event.remainingPlaces, remaining, JSON.stringify(overrides));
      assert.equal(event.availability, availability, JSON.stringify(overrides));
    }
  });

  it("tolere un DTO incomplet", () => {
    const event = toEvent({ id: 3 });
    assert.equal(event.title, "");
    assert.equal(event.registeredCount, 0);
    assert.deepEqual(event.categories, []);
    assert.equal(event.schoolRestricted, false);
  });
});

describe("toEvents", () => {
  it("convertit une liste et renvoie [] pour tout autre chose", () => {
    assert.equal(toEvents([eventDto(), eventDto({ id: 2 })]).length, 2);
    for (const value of [null, undefined, {}, "x"]) assert.deepEqual(toEvents(value), []);
  });
});

describe("toEventDto", () => {
  it("fait l'aller-retour exact avec toEvent", () => {
    assert.deepEqual(toEventDto(toEvent(eventDto())), eventDto());
    assert.deepEqual(toEventDto(toEvent(eventDto({ latitude: null, longitude: null }))),
      eventDto({ latitude: null, longitude: null }));
  });
});

describe("categoryMapper", () => {
  it("garde slug et libelle, libelle par defaut = slug", () => {
    assert.deepEqual(toCategory({ slug: "sport", label: "Sport" }), { slug: "sport", label: "Sport" });
    assert.deepEqual(toCategory({ slug: "sport" }), { slug: "sport", label: "sport" });
    assert.deepEqual(toCategory(null), { slug: "", label: "" });
    assert.deepEqual(toCategoryDto({ slug: "a", label: "A" }), { slug: "a", label: "A" });
  });
});
