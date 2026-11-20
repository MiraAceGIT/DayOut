/**
 * Seule la derniere tache lancee compte : en lancer une annule la precedente (requete abandonnee via `signal`,
 * resultat tardif ignore via `isCurrent()`). Ex. une recherche tapee pendant qu'une autre charge encore.
 */
export class LatestTask {
  constructor() {
    this.seq = 0;
    /** @type {AbortController | null} */
    this.controller = null;
  }

  /** @returns {{ signal: AbortSignal, isCurrent: () => boolean }} */
  start() {
    this.controller?.abort();
    const controller = new AbortController();
    this.controller = controller;
    const seq = ++this.seq;
    return { signal: controller.signal, isCurrent: () => seq === this.seq };
  }

  /** Plus aucune tache en cours n'est courante (ex. page quittee). */
  cancel() {
    this.seq += 1;
    this.controller?.abort();
    this.controller = null;
  }
}
