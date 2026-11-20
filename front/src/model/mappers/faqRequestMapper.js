import { toPageQuery } from "./pageQueryMapper.js";

/** Parametres de GET /faq : une page, dans l'ordre d'affichage par defaut de l'API.
 * @param {{ limit?: number, offset?: number }} [page] */
export function toFaqQuery(page = {}) {
  return toPageQuery(page);
}
