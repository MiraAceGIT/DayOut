import { PlaceholderView } from "../view/PlaceholderView.js";

export class PlaceholderController {
  constructor({ view = new PlaceholderView() } = {}) {
    this.view = view;
  }

  start({ title }) {
    if (!this.view.isReady) return;
    this.view.showTitle(title);
  }
}
