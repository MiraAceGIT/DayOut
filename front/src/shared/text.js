// Ne retire pas les espaces : highlight.js repere les occurrences caractere par caractere.
/** @param {unknown} text */
export function foldAccents(text) {
  return String(text ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

/** @param {unknown} text */
export function normalize(text) {
  return foldAccents(text).trim();
}
