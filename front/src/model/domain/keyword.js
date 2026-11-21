import { MAX_KEYWORD_LENGTH } from "../../core/apiLimits.js";

/**
 * Mot-cle de recherche tel qu'envoye a l'API (`q`) : sans espaces autour, coupe a MAX_KEYWORD_LENGTH.
 * Vide = pas de filtre. Seul endroit qui fixe cette regle (controleur de la carte et requete GET /events).
 * @param {unknown} query
 */
export function cleanKeyword(query) {
  return String(query ?? "").trim().slice(0, MAX_KEYWORD_LENGTH);
}
