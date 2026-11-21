export class LiveStatus {
  constructor(el) {
    this.el = el;
    /** @type {ReturnType<typeof setTimeout> | undefined} */
    this.timer = undefined;
  }

  set(message, { delay = 0 } = {}) {
    clearTimeout(this.timer);
    if (delay > 0) this.timer = setTimeout(() => { this.el.textContent = message; }, delay);
    else this.el.textContent = message;
  }

  cancel() {
    clearTimeout(this.timer);
  }
}
