import { frontUrl } from "../../../core/config.js";
import { isSafeUrl } from "../../../shared/dom.js";

const PLACEHOLDER = "assets/img/event-cover-placeholder.svg";

/**
 * @param {HTMLImageElement} img
 * @param {string|null|undefined} url
 * @returns {HTMLImageElement}
 */
export function setCover(img, url) {
  const fallback = frontUrl(PLACEHOLDER).href;
  img.onerror = () => {
    img.onerror = null; // pas de boucle si l'illustration manque aussi
    img.src = fallback;
  };
  img.src = url && isSafeUrl(url) ? url : fallback;
  return img;
}
