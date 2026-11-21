/** Region live permanente (role=status) : texte immediat, ou differe pour ne pas saturer le lecteur d'ecran. */
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

  /** Abandonne une annonce differee (page quittee). */
  cancel() {
    clearTimeout(this.timer);
  }
}
