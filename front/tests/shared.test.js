import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { formatCoordinates, formatDistance, formatEventWhen, formatHour, formatPrice, pluralize } from "../src/shared/format.js";
import { distanceKm, isLatLng } from "../src/shared/geo.js";
import { isInServiceArea } from "../src/models/serviceArea.js";
import { resultsMessage } from "../src/views/home/resultsMessage.js";
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
    assert.equal(formatHour(new Date(2026, 10, 15, 9, 0)), "9 h");
    assert.equal(formatHour(new Date(2026, 10, 15, 18, 5)), "18 h 05");
    assert.equal(formatHour("pas une date"), "");
  });

  it("formatEventWhen n'ajoute la fin que le meme jour", () => {
    const start = new Date(2026, 10, 15, 9);
    assert.match(formatEventWhen(start, new Date(2026, 10, 15, 21), { withEnd: true }), /· 9 h – 21 h$/);
    assert.match(formatEventWhen(start, new Date(2026, 10, 16, 2), { withEnd: true }), /· 9 h$/);
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

describe("serviceArea", () => {
  it("suit le contour de l'Ile-de-France, pas son rectangle", () => {
    const at = (lat, lng) => isInServiceArea({ lat, lng });
    assert.equal(at(48.8455, 2.3275), true, "Paris");
    assert.equal(at(48.404, 2.7), true, "Fontainebleau");
    assert.equal(at(49.19, 2.47), false, "Chantilly (Oise), dans le rectangle");
    assert.equal(at(45.76, 4.83), false, "Lyon");
    assert.equal(isInServiceArea(null), false);
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
