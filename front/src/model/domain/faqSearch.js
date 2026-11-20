import { normalize } from "./eventFilters.js";

/** Meme plafond que la regle `q` de l'API (back : FaqFindQuery::RULES). */
export const MAX_FAQ_QUERY_LENGTH = 100;

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
