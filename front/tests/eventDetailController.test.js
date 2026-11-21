import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { toEvent } from "../src/model/mappers/eventMapper.js";
import { eventDto } from "./fixtures.js";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { EventDetailController } = await import("../src/controller/EventDetailController.js");
const { ApiError } = await import("../src/core/api.js");
delete globalThis.window;

function viewStub() {
  return {
    isReady: true, state: "idle", event: null, error: null, busy: false,
    handlers: { onRetry: async () => {} },
    bind(handlers) { this.handlers = handlers; },
    showLoading() { this.state = "loading"; this.busy = true; this.event = null; this.error = null; },
    endLoading() { this.busy = false; },
    showEvent(event) { this.event = event; this.state = "event"; },
    showNotFound() { this.event = null; this.state = "404"; },
    showError(message) { this.event = null; this.error = message; this.state = "error"; },
  };
}

const detail = () => toEvent({ ...eventDto(), creator: {
  id: 2, display_name: "Garage ISEP", description: null, avatar_url: null, school_name: null,
} });

describe("identifiant d'evenement", () => {
  it("transmet l'identifiant de l'URL tel quel (grands identifiants sans perte de precision)", async () => {
    for (const id of ["1", "9223372036854775807", "abc"]) {
      let requestedId;
      const controller = new EventDetailController({ view: viewStub(), loadEvent: async (value) => { requestedId = value; return detail(); } });
      await controller.start({ id });
      assert.equal(requestedId, id);
    }
  });

  it("affiche la page 404 sans appeler l'API quand l'identifiant est absent", async () => {
    for (const id of [null, ""]) {
      const view = viewStub();
      let calls = 0;
      const controller = new EventDetailController({ view, loadEvent: async () => { calls++; return detail(); } });
      await controller.start({ id });
      assert.equal(view.state, "404", String(id));
      assert.equal(view.busy, false);
      assert.equal(calls, 0);
    }
  });
});

describe("controleur de detail", () => {
  it("charge le bon identifiant et affiche les donnees de l'API", async () => {
    const view = viewStub();
    let requestedId;
    const controller = new EventDetailController({ view, loadEvent: async id => { requestedId = id; return detail(); } });
    await controller.start({ id: "1" });
    assert.equal(requestedId, "1");
    assert.equal(view.state, "event");
    assert.equal(view.event.id, 1);
    assert.equal(view.busy, false);
  });

  it("affiche la meme page 404 pour les evenements inexistants ou masques", async () => {
    for (const id of ["9999", "8", "9", "10"]) {
      const view = viewStub();
      const controller = new EventDetailController({ view, loadEvent: async () => { throw new ApiError("Event not found", { status: 404 }); } });
      await controller.start({ id });
      assert.equal(view.state, "404");
      assert.equal(view.event, null);
      assert.equal(view.busy, false);
    }
  });

  it("distingue une panne d'une 404 et permet de reessayer", async () => {
    for (const error of [new ApiError("Offline", { type: "network" }), new ApiError("Timeout", { type: "timeout" }), new ApiError("Internal", { status: 500 })]) {
      const view = viewStub();
      let calls = 0;
      const controller = new EventDetailController({ view, loadEvent: async () => {
        if (++calls === 1) throw error;
        return detail();
      } });
      await controller.start({ id: "1" });
      assert.equal(view.state, "error");
      assert.ok(view.error);
      assert.equal(view.busy, false);
      await view.handlers.onRetry();
      assert.equal(view.state, "event");
      assert.equal(calls, 2);
    }
  });
});
