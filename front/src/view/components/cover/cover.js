import { frontUrl } from "../../../core/config.js";
import { isSafeUrl } from "../../../shared/dom.js";

const PLACEHOLDER = "assets/img/event-cover-placeholder.svg";

/**
 * Pose la couverture d'un evenement sur `img` : l'image de l'API si son URL est sure, sinon l'illustration
 * par defaut, qui remplace aussi une image qui ne se charge pas (une seule fois : pas de boucle si elle manque).
 * @param {HTMLImageElement} img
 * @param {string|null|undefined} url
 * @returns {HTMLImageElement}
 */
export function setCover(img, url) {
  const fallback = frontUrl(PLACEHOLDER).href;
  img.onerror = () => {
    img.onerror = null;
    img.src = fallback;
  };
  img.src = url && isSafeUrl(url) ? url : fallback;
  return img;
}
