import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { toEvent } from "../src/model/mappers/eventMapper.js";
import { initials, presentEventDetail } from "../src/view/event/eventDetailPresenter.js";
import { errorStatus, paginationStatus } from "../src/view/events/eventsMessages.js";
import { faqStatus } from "../src/view/faq/faqMessages.js";
import { creatorDto, eventDto } from "./fixtures.js";

describe("messages de la liste des events", () => {
  it("annonce le nombre affiche, la fin de liste et la limite de consultation", () => {
    assert.equal(paginationStatus(0, { hasMore: false }), "Aucun événement à venir.");
    assert.equal(paginationStatus(2, { hasMore: true }), "2 événements à venir affichés.");
    assert.equal(paginationStatus(1, { hasMore: false }), "1 événement à venir affiché. Tous les événements ont été affichés.");
    assert.equal(paginationStatus(3, { hasMore: false, limitReached: true }), "3 événements à venir affichés. La limite de consultation est atteinte.");
    assert.equal(errorStatus(0), "");
    assert.equal(errorStatus(2), "2 événements déjà affichés.");
  });
});

describe("messages de la FAQ", () => {
  it("decrit le resultat du filtre", () => {
    assert.equal(faqStatus(0, 0, ""), "Aucune question disponible pour le moment.");
    assert.equal(faqStatus(3, 3, "  "), "");
    assert.equal(faqStatus(0, 3, "zzz"), "Aucune question ne correspond à « zzz ».");
    assert.equal(faqStatus(2, 5, " compte "), "2 questions sur 5 correspondent à « compte ».");
  });
});

describe("presenter de la page detail", () => {
  it("compose adresse, capacite, prix et createur", () => {
    const view = presentEventDetail(toEvent({
      ...eventDto({ registered_count: 3, capacity: 10, address_line: "10 rue X", postal_code: "75015", city: "Paris" }),
      creator: creatorDto(),
    }));
    assert.equal(view.address, "10 rue X, 75015 Paris");
    assert.deepEqual(view.capacity, { max: 10, value: 3, label: "3 / 10" });
    assert.match(view.registrations, /^3 inscrits sur 10 places$/);
    assert.equal(view.creator.initials, initials(view.creator.name));
    assert.ok(view.tags.at(-1) === "Gratuit" || view.tags.at(-1) === "Payant");
  });

  it("gere capacite illimitee, lieu inconnu et evenement complet", () => {
    const view = presentEventDetail(toEvent({
      ...eventDto({ capacity: null, venue_name: null, city: null, address_line: null, postal_code: null }),
      creator: creatorDto(),
    }));
    assert.equal(view.capacity, null);
    assert.equal(view.place, "Lieu à préciser");
    assert.equal(view.address, "Adresse à préciser");
    assert.match(view.practicalSummary, /^Capacité illimitée · /);

    const full = presentEventDetail(toEvent({ ...eventDto({ capacity: 2, registered_count: 2 }), creator: creatorDto() }));
    assert.equal(full.registerLabel, "Complet");
    assert.equal(initials("  garage   isep club "), "GI");
  });
});
