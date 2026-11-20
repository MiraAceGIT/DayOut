import { isApiError, messageFor } from "../core/errors.js";

/**
 * @typedef {object} LoadingView
 * @property {() => void} showLoading
 * @property {() => void} endLoading
 * @property {(message: string) => void} showError
 */

/**
 * Cycle d'un chargement affiche par une view : un seul a la fois, chargement -> succes ou message d'erreur,
 * et plus rien n'est touche une fois la page quittee (`signal` du routeur annule).
 * Un echec d'API est attendu (message a l'utilisateur) ; toute autre erreur est un bug, aussi journalise.
 */
export class PageLoader {
  /** @param {LoadingView} view @param {{ signal?: AbortSignal }} [options] */
  constructor(view, { signal } = {}) {
    this.view = view;
    this.signal = signal;
    this.busy = false;
  }

  /** Faux une fois la page quittee : une reponse tardive est ignoree. */
  get isActive() {
    return !this.signal?.aborted;
  }

  /**
   * Sans effet si un chargement est deja en cours ou la page quittee.
   * `onError` traite lui-meme une erreur attendue (ex. 404) en renvoyant true ; sinon message generique.
   * @template T
   * @param {(signal: AbortSignal | undefined) => Promise<T>} task
   * @param {{ onSuccess: (result: T) => void, onError?: (error: unknown) => boolean }} handlers
   * @returns {Promise<void>}
   */
  async run(task, { onSuccess, onError }) {
    if (this.busy || !this.isActive) return;
    this.busy = true;
    this.view.showLoading();
    try {
      const result = await task(this.signal);
      if (this.isActive) onSuccess(result);
    } catch (error) {
      if (!this.isActive || onError?.(error)) return;
      if (!isApiError(error)) console.error(error);
      this.view.showError(messageFor(error));
    } finally {
      this.busy = false;
      if (this.isActive) this.view.endLoading();
    }
  }
}
