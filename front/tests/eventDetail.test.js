import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { toEventDetail } from "../src/model/mappers/eventDetailMapper.js";
import { eventDto } from "./fixtures.js";

const creator = { id: 2, display_name: "Garage ISEP", description: "Club étudiant", avatar_url: null, school_name: "ISEP" };

describe("detail d'evenement", () => {
  it("normalise les donnees de l'evenement et le profil public du createur", () => {
    const detail = toEventDetail({ ...eventDto({ registered_count: 3 }), creator });
    assert.equal(detail.id, 1);
    assert.equal(detail.registeredCount, 3);
    assert.equal(detail.remainingPlaces, 247);
    assert.deepEqual(detail.creator, { id: 2, displayName: "Garage ISEP", description: "Club étudiant", avatarUrl: null, schoolName: "ISEP" });
  });

  it("conserve les champs optionnels absents du profil public", () => {
    const detail = toEventDetail({ ...eventDto(), creator: { ...creator, description: null, school_name: null } });
    assert.equal(detail.creator.description, null);
    assert.equal(detail.creator.schoolName, null);
  });
});
