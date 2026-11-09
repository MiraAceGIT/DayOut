import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { remainingPlaces, availabilityOf, LAST_PLACES_THRESHOLD } from "../src/model/domain/Event.js";
import { toEvent } from "../src/model/mappers/eventMapper.js";
import { eventDto } from "./fixtures.js";

describe("places restantes", () => {
  it("soustrait les inscriptions et borne le resultat a zero", () => {
    assert.equal(remainingPlaces(60, 57), 3);
    assert.equal(remainingPlaces(60, 59), 1);
    assert.equal(remainingPlaces(60, 60), 0);
    assert.equal(remainingPlaces(60, 61), 0);
    assert.equal(remainingPlaces(60, 0), 60);
    assert.equal(remainingPlaces(60, undefined), 60);
    assert.equal(remainingPlaces("60", "57"), 3);
  });

  it("distingue une capacite illimitee d'un evenement complet", () => {
    assert.equal(remainingPlaces(null, 100), null);
    assert.equal(remainingPlaces(undefined, 100), null);
    assert.equal(availabilityOf(null), null);
    assert.equal(availabilityOf(0), "full");
  });

  it("signale les dernieres places jusqu'au seuil inclus", () => {
    assert.equal(availabilityOf(1), "last");
    assert.equal(availabilityOf(LAST_PLACES_THRESHOLD), "last");
    assert.equal(availabilityOf(LAST_PLACES_THRESHOLD + 1), "open");
  });

  it("applique la meme regle a la liste et au detail a partir du compteur de l'API", () => {
    for (const [capacity, registeredCount, expected] of [[60, 57, 3], [60, 60, 0], [60, 65, 0], [null, 57, null]]) {
      const dto = eventDto({ capacity, registered_count: registeredCount });
      const item = toEvent(dto);
      const detail = toEvent({ ...dto, creator: { id: 2, display_name: "Garage", description: null, avatar_url: null, school_name: null } });
      assert.equal(item.remainingPlaces, expected);
      assert.equal(detail.remainingPlaces, expected);
      assert.equal(item.availability, detail.availability);
    }
  });
});
