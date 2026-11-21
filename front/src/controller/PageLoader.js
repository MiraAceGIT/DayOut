import { isApiError, messageFor } from "../core/errors.js";

/**
 * @typedef {object} LoadingView
 * @property {() => void} showLoading
 * @property {() => void} endLoading
 * @property {(message: string) => void} showError
 */

export class PageLoader {
  /** @param {LoadingView} view @param {{ signal?: AbortSignal }} [options] */
  constructor(view, { signal } = {}) {
    this.view = view;
    this.signal = signal;
    this.busy = false;
  }

  get isActive() {
    return !this.signal?.aborted;
  }

  /**
   * `onError` renvoie true quand il a lui-meme traite l'erreur.
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
