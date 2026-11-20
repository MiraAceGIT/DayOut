/**
 * Forme de comparaison d'un texte : sans accents, en minuscules, sans espaces autour.
 * @param {unknown} text
 */
export function normalize(text) {
  return String(text ?? "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().trim();
}
