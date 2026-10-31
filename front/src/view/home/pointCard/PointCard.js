import { formatCoordinates } from "../../../shared/format.js";

const SOURCE_LABELS = {
  default: "ISEP – Notre-Dame-des-Champs",
  device: "Ta position",
};

export class PointCard {
  constructor({ labelEl, changeBtn, radiusEl, radiusOut }) {
    this.labelEl = labelEl;
    this.changeBtn = changeBtn;
    this.radiusEl = radiusEl;
    this.radiusOut = radiusOut;
  }

  get isReady() {
    return Boolean(this.labelEl && this.radiusEl && this.radiusOut);
  }

  bind({ onRadius, onChangePoint }) {
    this.radiusEl.addEventListener("input", () => onRadius(Number(this.radiusEl.value)));
    this.changeBtn?.addEventListener("click", () => onChangePoint());
  }

  setOrigin(point) {
    this.labelEl.textContent = point.label
      || SOURCE_LABELS[point.source]
      || `Point sur la carte (${formatCoordinates(point)})`;
  }

  setRadius(km) {
    const min = Number(this.radiusEl.min) || 0;
    const max = Number(this.radiusEl.max) || 100;
    this.radiusEl.value = String(km);
    this.radiusEl.setAttribute("aria-valuetext", `${km} km`);
    this.radiusEl.style.setProperty("--range-progress", `${((km - min) / (max - min)) * 100}%`);
    this.radiusOut.textContent = `${km} km`;
  }
}
