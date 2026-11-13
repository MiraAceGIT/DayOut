import { h } from "./dom.js";

const fold = (char) => char.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

/** Texte sans accents ni casse, avec pour chaque caractere replie l'indice du caractere d'origine. */
function foldWithOrigin(chars) {
  let folded = "";
  const origin = [];
  chars.forEach((char, index) => {
    const part = fold(char);
    folded += part;
    for (let i = 0; i < part.length; i += 1) origin.push(index);
  });
  return { folded, origin };
}

/**
 * Decoupe `text` en segments ; `match` vaut true pour les occurrences de `keyword`
 * (sans tenir compte de la casse ni des accents).
 * @param {string|null|undefined} text
 * @param {string|null|undefined} keyword
 * @returns {{ text: string, match: boolean }[]}
 */
export function splitMatches(text, keyword) {
  const source = String(text ?? "");
  const needle = [...String(keyword ?? "").trim()].map(fold).join("");
  if (!source || !needle) return source ? [{ text: source, match: false }] : [];

  const chars = [...source];
  const { folded, origin } = foldWithOrigin(chars);
  const segments = [];
  let next = 0; // premier caractere d'origine pas encore emis

  for (let at = folded.indexOf(needle); at !== -1; at = folded.indexOf(needle, at + needle.length)) {
    const start = origin[at];
    const end = origin[at + needle.length - 1] + 1;
    if (start < next) continue; // chevauchement dans un meme caractere d'origine
    if (start > next) segments.push({ text: chars.slice(next, start).join(""), match: false });
    segments.push({ text: chars.slice(start, end).join(""), match: true });
    next = end;
  }
  if (next < chars.length) segments.push({ text: chars.slice(next).join(""), match: false });
  return segments;
}

/** Noeuds pour h() : <mark> autour des occurrences, texte brut ailleurs (une saisie `<script>` reste du texte). */
export function highlight(text, keyword) {
  return splitMatches(text, keyword).map((s) => (s.match ? h("mark", { class: "highlight" }, s.text) : s.text));
}

/** Extrait de `text` centre sur la premiere occurrence de `keyword` ; null si le texte ne la contient pas. */
export function excerptAround(text, keyword, { before = 40, length = 140 } = {}) {
  const segments = splitMatches(text, keyword);
  const first = segments.findIndex((s) => s.match);
  if (first === -1) return null;

  const chars = [...String(text)];
  const matchAt = segments.slice(0, first).reduce((n, s) => n + [...s.text].length, 0);
  const start = Math.max(0, matchAt - before);
  const end = Math.min(chars.length, start + length);
  return `${start > 0 ? "…" : ""}${chars.slice(start, end).join("")}${end < chars.length ? "…" : ""}`;
}
