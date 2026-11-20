import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { LatestTask } from "../src/controller/LatestTask.js";
import { OriginPicker } from "../src/controller/OriginPicker.js";

const PARIS = { lat: 48.8566, lng: 2.3522 };
const LYON = { lat: 45.764, lng: 4.8357 };

function mapStub() {
  return {
    messages: /** @type {string[]} */ ([]),
    showOutOfArea(/** @type {string} */ _area, /** @type {string} */ from) { this.messages.push(`out:${from}`); },
    clearMessage() { this.messages.push("clear"); },
    showLocating() { this.messages.push("locating"); },
    showLocated() { this.messages.push("located"); },
    showLocateError(/** @type {string|undefined} */ code) { this.messages.push(`error:${code}`); },
  };
}

describe("LatestTask", () => {
  it("annule la tache precedente et ne garde que la plus recente", () => {
    const tasks = new LatestTask();
    const first = tasks.start();
    const second = tasks.start();
    assert.equal(first.signal.aborted, true);
    assert.equal(first.isCurrent(), false);
    assert.equal(second.isCurrent(), true);
    tasks.cancel();
    assert.equal(second.signal.aborted, true);
    assert.equal(second.isCurrent(), false);
  });
});

describe("OriginPicker", () => {
  it("accepte un point de la zone couverte et refuse un point hors zone", () => {
    const map = mapStub();
    const origins = [];
    const picker = new OriginPicker({ map, onOrigin: (point) => origins.push(point) });
    picker.pick({ ...PARIS, source: "map" });
    picker.pick({ ...LYON, source: "map" });
    assert.deepEqual(origins, [{ ...PARIS, source: "map" }]);
    assert.deepEqual(map.messages, ["clear", "out:map"]);
  });

  it("utilise la position de l'appareil, ou signale l'erreur de localisation", async () => {
    const map = mapStub();
    const origins = [];
    await new OriginPicker({ map, onOrigin: (p) => origins.push(p), getPosition: async () => PARIS }).locate();
    assert.deepEqual(origins, [{ ...PARIS, source: "device" }]);
    assert.deepEqual(map.messages, ["locating", "located"]);

    const denied = mapStub();
    await new OriginPicker({ map: denied, onOrigin: () => assert.fail(), getPosition: () => Promise.reject({ code: "denied" }) }).locate();
    assert.deepEqual(denied.messages, ["locating", "error:denied"]);
  });

  it("ignore une position arrivee apres un clic ou apres le depart de la page", async () => {
    for (const interrupt of ["pick", "cancel"]) {
      const map = mapStub();
      const origins = [];
      /** @type {(p: typeof PARIS) => void} */
      let resolve = () => {};
      const picker = new OriginPicker({ map, onOrigin: (p) => origins.push(p), getPosition: () => new Promise((done) => { resolve = done; }) });
      const locating = picker.locate();
      if (interrupt === "pick") picker.pick({ ...PARIS, source: "map" });
      else picker.cancel();
      resolve({ lat: 48.9, lng: 2.4 });
      await locating;
      assert.deepEqual(origins, interrupt === "pick" ? [{ ...PARIS, source: "map" }] : [], interrupt);
      assert.equal(map.messages.includes("located"), false, interrupt);
    }
  });
});
