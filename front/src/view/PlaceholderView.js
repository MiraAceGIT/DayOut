export class PlaceholderView {
  constructor(root = document) {
    this.titleEl = root.querySelector("#placeholder-title");
  }

  get isReady() {
    return Boolean(this.titleEl);
  }

  showTitle(title) {
    this.titleEl.textContent = title;
  }
}
