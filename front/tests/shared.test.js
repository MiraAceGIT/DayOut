import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { formatCoordinates, formatDate, formatDay, formatDistance, formatEventWhen, formatHour, formatPrice, formatRemainingPlaces, pluralize } from "../src/shared/format.js";
import { distanceKm, isLatLng } from "../src/shared/geo.js";
import { isInCoverageArea } from "../src/model/domain/coverageArea.js";
import { resultsMessage } from "../src/view/home/resultsMessage.js";
import { spaces } from "./fixtures.js";

describe("format", () => {
  it("formatPrice : gratuit, centimes, devise invalide, montant absent", () => {
    assert.equal(spaces(formatPrice(1500)), "15 €");
    assert.equal(spaces(formatPrice(1550)), "15,50 €");
    assert.equal(formatPrice(0, { isPaid: false }), "Gratuit");
    assert.equal(spaces(formatPrice(100, { currency: "XX" })), "1 XX", "pas d'exception");
    assert.equal(formatPrice(null), "");
  });

  it("formatHour suit la typographie de la charte", () => {
    assert.equal(formatHour("2026-11-15T08:00:00Z"), "9 h");
    assert.equal(formatHour("2026-11-15T17:05:00Z"), "18 h 05");
    assert.equal(formatHour("2026-11-14T23:00:00Z"), "0 h");
    assert.equal(formatHour("pas une date"), "");
  });

  it("formatEventWhen affiche une plage horaire ou les deux dates selon le jour a Paris", () => {
    const start = "2026-11-15T08:00:00Z";
    assert.equal(formatEventWhen(start, "2026-11-15T20:00:00Z", { withEnd: true }), "Dim. 15 nov. · 9 h – 21 h");
    assert.equal(formatEventWhen(start, "2026-11-16T01:00:00Z", { withEnd: true }), "Dim. 15 nov. · 9 h – Lun. 16 nov. · 2 h");
    assert.equal(formatEventWhen("2026-11-07T23:30:00Z", "2026-11-08T02:00:00Z", { withEnd: true }), "Dim. 8 nov. · 0 h 30 – 3 h");
    assert.equal(formatEventWhen(start, "2026-11-15T20:00:00Z"), "Dim. 15 nov. · 9 h");
  });

  it("utilise les jours et mois francais, l'annee sur le detail et aux changements d'annee", () => {
    assert.equal(formatDay("2026-11-15T08:00:00Z", { withYear: true }), "Dim. 15 nov. 2026");
    assert.equal(formatEventWhen("2026-11-15T08:00:00Z", "2026-11-15T20:00:00Z", { withEnd: true, withYear: true }), "Dim. 15 nov. 2026 · 9 h – 21 h");
    assert.equal(formatEventWhen("2026-12-31T22:00:00Z", "2027-01-01T01:00:00Z", { withEnd: true }), "Jeu. 31 déc. 2026 · 23 h – Ven. 1 janv. 2027 · 2 h");
    assert.equal(spaces(formatDate("2026-11-15T08:00:00Z", { options: { dateStyle: "long" } })), "15 novembre 2026");
  });

  it("applique le fuseau de Paris en hiver et en ete, independamment du fuseau du navigateur", () => {
    assert.equal(formatHour("2026-01-15T08:00:00Z"), "9 h");
    assert.equal(formatHour("2026-07-15T08:00:00Z"), "10 h");
    assert.equal(formatHour("2026-03-29T00:30:00Z"), "1 h 30");
    assert.equal(formatHour("2026-03-29T01:30:00Z"), "3 h 30");
    assert.equal(formatHour("2026-10-25T00:30:00Z"), "2 h 30");
    assert.equal(formatHour("2026-10-25T02:30:00Z"), "3 h 30");
  });

  it("tolere une date absente ou invalide et ne montre pas une fin anterieure au debut", () => {
    assert.equal(formatEventWhen("invalide", "2026-11-15T20:00:00Z", { withEnd: true }), "");
    assert.equal(formatDay(null), "");
    assert.equal(formatDate(null, { fallback: "Date à préciser" }), "Date à préciser");
    const start = "2026-11-15T08:00:00Z";
    for (const end of [null, "invalide", "2026-11-15T07:00:00Z"]) {
      assert.equal(formatEventWhen(start, end, { withEnd: true }), "Dim. 15 nov. · 9 h");
    }
  });

  it("partage les libelles de places entre cartes et detail", () => {
    assert.equal(formatRemainingPlaces(null), "Places illimitées");
    assert.equal(formatRemainingPlaces(0), "Complet");
    assert.equal(formatRemainingPlaces(1), "1 place restante");
    assert.equal(formatRemainingPlaces(3), "3 places restantes");
    assert.equal(formatRemainingPlaces(50), "50 places restantes");
  });

  it("formatDistance, formatCoordinates, pluralize", () => {
    assert.equal(spaces(formatDistance(0.64)), "0,6 km");
    assert.equal(spaces(formatDistance(12.4)), "12 km");
    assert.equal(formatDistance(NaN), "");
    assert.equal(formatCoordinates({ lat: 48.84551, lng: 2.3275 }), "48,8455 ; 2,3275");
    assert.equal(pluralize(1, "place"), "1 place");
    assert.equal(pluralize(3, "place"), "3 places");
  });
});

describe("geo", () => {
  it("distanceKm : Paris -> Lyon a vol d'oiseau", () => {
    const km = distanceKm({ lat: 48.8566, lng: 2.3522 }, { lat: 45.764, lng: 4.8357 });
    assert.ok(km > 390 && km < 395, String(km));
    assert.equal(distanceKm({ lat: 1, lng: 1 }, { lat: 1, lng: 1 }), 0);
  });

  it("isLatLng refuse ce qui n'est pas une coordonnee WGS84", () => {
    assert.equal(isLatLng({ lat: 48.8, lng: 2.3 }), true);
    for (const p of [null, {}, { lat: 91, lng: 0 }, { lat: "48", lng: 2 }, { lat: NaN, lng: 0 }]) {
      assert.equal(isLatLng(p), false, JSON.stringify(p));
    }
  });
});

describe("coverageArea", () => {
  it("suit le contour de l'Ile-de-France, pas son rectangle", () => {
    const at = (lat, lng) => isInCoverageArea({ lat, lng });
    assert.equal(at(48.8455, 2.3275), true, "Paris");
    assert.equal(at(48.404, 2.7), true, "Fontainebleau");
    assert.equal(at(49.19, 2.47), false, "Chantilly (Oise), dans le rectangle");
    assert.equal(at(45.76, 4.83), false, "Lyon");
    assert.equal(isInCoverageArea(null), false);
  });
});

describe("resultsMessage", () => {
  it("compte le cercle, les sans-position, le plafond et propose d'elargir", () => {
    assert.equal(resultsMessage({ items: [1, 2], inCircle: 2, withoutPosition: 0 }), "2 événements dans ce cercle");
    assert.equal(
      resultsMessage({ items: [], inCircle: 0, withoutPosition: 0 }),
      "Aucun événement dans ce cercle. Élargis le rayon ou retire des filtres."
    );
    assert.match(resultsMessage({ items: [1, 2], inCircle: 1, withoutPosition: 1, loadedCap: 100 }),
      /1 événement dans ce cercle · 1 sans position sur la carte · au moins 100 événements chargés/);
  });
});
