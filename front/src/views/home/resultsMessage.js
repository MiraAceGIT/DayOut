import { pluralize } from "../../shared/format.js";

/**
 * Texte du statut des resultats (role=status), sans DOM.
 * `loadedCap` : plafond de l'API atteint (nombre charge), des evenements a venir peuvent manquer ; 0 sinon.
 */
export function resultsMessage({ items, inCircle, withoutPosition, loadedCap = 0 }) {
  let message = inCircle === 0
    ? "Aucun événement dans ce cercle"
    : `${pluralize(inCircle, "événement")} dans ce cercle`;
  if (withoutPosition > 0) message += ` · ${withoutPosition} sans position sur la carte`;
  if (loadedCap > 0) message += ` · au moins ${loadedCap} événements chargés, liste peut-être incomplète`;
  if (items.length === 0) message += ". Élargis le rayon ou retire des filtres.";
  return message;
}
