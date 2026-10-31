import { isLatLng } from "../../shared/geo.js";
import { ILE_DE_FRANCE_OUTLINE } from "./ileDeFranceOutline.js";

/**
 * Zone couverte par DayOut : l'Ile-de-France. `outline` = son contour [lat, lng] ;
 * south/west/north/east = son rectangle englobant, pour cadrer la carte et ecarter vite les points lointains.
 */
export const SERVICE_AREA = Object.freeze({
  name: "Île-de-France",
  outline: ILE_DE_FRANCE_OUTLINE,
  south: 48.12,
  west: 1.44,
  north: 49.25,
  east: 3.56,
});

/** Lancer de rayon : vrai si le point est a l'interieur du polygone `ring` ([lat, lng], ferme implicitement). */
function insideRing({ lat, lng }, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [latI, lngI] = ring[i];
    const [latJ, lngJ] = ring[j];
    if ((latI > lat) !== (latJ > lat) && lng < ((lngJ - lngI) * (lat - latI)) / (latJ - latI) + lngI) {
      inside = !inside;
    }
  }
  return inside;
}

/** Vrai si `point` = { lat, lng } est dans la zone couverte. */
export function isInServiceArea(point, area = SERVICE_AREA) {
  return isLatLng(point)
    && point.lat >= area.south && point.lat <= area.north
    && point.lng >= area.west && point.lng <= area.east
    && insideRing(point, area.outline);
}
