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

  cancel() {
    this.seq += 1;
    this.controller?.abort();
    this.controller = null;
  }
}
