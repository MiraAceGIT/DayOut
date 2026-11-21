/**
 * Texte sans accents et en minuscules, longueur et espaces conserves caractere par caractere
 * (highlight.js s'en sert pour retrouver la position d'origine d'une occurrence).
 * @param {unknown} text
 */
export function foldAccents(text) {
  return String(text ?? "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
}

/**
 * Forme de comparaison d'un texte : sans accents, en minuscules, sans espaces autour.
 * @param {unknown} text
 */
export function normalize(text) {
  return foldAccents(text).trim();
}
