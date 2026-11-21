import { currentPosition } from "../core/geolocation.js";
import { COVERAGE_AREA, isInCoverageArea } from "../model/domain/coverageArea/coverageArea.js";
import { LatestTask } from "../shared/LatestTask.js";

export class OriginPicker {
  /**
   * @param {{
   *   map: { showOutOfArea(areaName: string, from: string): void, clearMessage(): void, showLocating(): void,
   *          showLocated(): void, showLocateError(code: string | undefined): void },
   *   onOrigin: (point: { lat: number, lng: number, source?: string, label?: string }) => void,
   *   getPosition?: typeof currentPosition,
   * }} options
   */
  constructor({ map, onOrigin, getPosition = currentPosition }) {
    this.map = map;
    this.onOrigin = onOrigin;
    this.getPosition = getPosition;
    this.picks = new LatestTask();
  }

  /** @param {{ lat: number, lng: number, source?: string, label?: string }} point */
  pick(point) {
    this.picks.start();
    if (!isInCoverageArea(point)) {
      this.map.showOutOfArea(COVERAGE_AREA.name, "map");
      return;
    }
    this.map.clearMessage();
    this.onOrigin(point);
  }

  async locate() {
    const pick = this.picks.start();
    this.map.showLocating();
    try {
      const position = await this.getPosition();
      if (!pick.isCurrent()) return;
      if (!isInCoverageArea(position)) {
        this.map.showOutOfArea(COVERAGE_AREA.name, "device");
        return;
      }
      this.onOrigin({ ...position, source: "device" });
      this.map.showLocated();
    } catch (err) {
      if (pick.isCurrent()) this.map.showLocateError(err?.code);
    }
  }

  cancel() {
    this.picks.cancel();
  }
}
