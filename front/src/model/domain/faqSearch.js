import { normalize } from "../../shared/text.js";

/**
 * Questions dont la question ou la reponse contient `query`, sans tenir compte
 * de la casse ni des accents ; l'ordre d'affichage est conserve.
 * @template {{ question: string, answer: string }} T
 * @param {T[]} entries
 * @param {string} [query]
 * @returns {T[]}
 */
export function searchFaq(entries, query = "") {
  const needle = normalize(query);
  if (!needle) return entries;
  return entries.filter((entry) => normalize(entry.question).includes(needle) || normalize(entry.answer).includes(needle));
}
