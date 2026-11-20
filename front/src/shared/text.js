/**
 * Forme de comparaison d'un texte : sans accents, en minuscules, sans espaces autour.
 * @param {unknown} text
 */
export function normalize(text) {
  return String(text ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
}
