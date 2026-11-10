import { pluralize } from "../../shared/format.js";

/** `keyword` / `total` : recherche par mot-cle ; `total` = resultats de l'API avant rayon et filtres. */
export function resultsMessage({ items, inCircle, withoutPosition, loadedCap = 0, keyword = "", total = 0 }) {
  if (keyword && total === 0) {
    return `Aucun résultat pour « ${keyword} ». Élargis ta recherche : essaie un mot plus court, une autre orthographe ou le nom d'une catégorie.`;
  }
  let message;
  if (keyword) {
    message = inCircle === 0
      ? `Aucun résultat pour « ${keyword} » dans ce cercle`
      : `${pluralize(inCircle, "résultat")} pour « ${keyword} » dans ce cercle`;
    if (total > items.length) message += ` · ${total} au total`;
  } else {
    message = inCircle === 0
      ? "Aucun événement dans ce cercle"
      : `${pluralize(inCircle, "événement")} dans ce cercle`;
  }
  if (withoutPosition > 0) message += ` · ${withoutPosition} sans position sur la carte`;
  if (loadedCap > 0) message += ` · au moins ${loadedCap} événements chargés, liste peut-être incomplète`;
  if (items.length === 0) message += ". Élargis le rayon ou retire des filtres.";
  return message;
}
