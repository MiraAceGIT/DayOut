import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { creatorDto, eventDetail as event, eventDto } from "./fixtures.js";

// Le controleur importe la configuration navigateur ; la vue sera injectee.
globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { EventsController } = await import("../src/controller/EventsController.js");
const { fetchUpcomingEventPage, fetchUpcomingEvents, fetchEvent } = await import("../src/model/services/eventService.js");
const { ApiError } = await import("../src/core/api.js");
delete globalThis.window;

function viewStub() {
  return {
    isReady: true, items: [], error: null, pagination: null, busy: false,
    handlers: { onLoadMore: async () => {} },
    bind(handlers) { this.handlers = handlers; },
    showLoading() { this.busy = true; this.error = null; },
    endLoading() { this.busy = false; },
    appendEvents(items) { this.items.push(...items); },
    showPagination(value) { this.pagination = value; },
    showError(error) { this.error = error; },
  };
}

describe("pagination de la liste", () => {
  it("ajoute les pages avec un offset et un instant de reference stables, puis s'arrete", async () => {
    const view = viewStub();
    const calls = [];
    const controller = new EventsController({ view, limit: 2, loadEvents: async (options) => {
      calls.push(options);
      return { items: options.offset === 0 ? [event({ id: 1 }), event({ id: 2 })] : [event({ id: 3 })],
        offset: options.offset, limit: 2, hasMore: options.offset === 0, total: 3 };
    } });
    await controller.start();
    await view.handlers.onLoadMore();
    await controller.loadMore();
    assert.deepEqual(calls.map(call => call.offset), [0, 2]);
    assert.equal(calls[0].from, calls[1].from);
    assert.ok(Number.isFinite(Date.parse(calls[0].from)));
    assert.deepEqual(view.items.map(item => item.id), [1, 2, 3]);
    assert.equal(view.pagination.hasMore, false);
    assert.equal(view.busy, false);
  });

  it("conserve les cartes apres une erreur et reessaie le meme offset", async () => {
    const view = viewStub();
    const offsets = [];
    let fail = true;
    const controller = new EventsController({ view, limit: 1, loadEvents: async ({ offset }) => {
      offsets.push(offset);
      if (offset === 1 && fail) { fail = false; throw new ApiError("Offline", { type: "network" }); }
      return { items: [event({ id: offset + 1 })], offset, limit: 1, hasMore: offset === 0, total: 2 };
    } });
    await controller.start();
    await controller.loadMore();
    assert.deepEqual(view.items.map(item => item.id), [1]);
    assert.ok(view.error);
    assert.equal(view.busy, false);
    await controller.loadMore();
    assert.deepEqual(offsets, [0, 1, 1]);
    assert.deepEqual(view.items.map(item => item.id), [1, 2]);
    assert.equal(view.error, null);
  });

  it("ignore les doubles clics pendant une requete et gere une liste vide", async () => {
    const view = viewStub();
    /** @type {(page: Awaited<ReturnType<typeof fetchUpcomingEventPage>>) => void} */
    let resolvePage = () => {};
    let calls = 0;
    const controller = new EventsController({ view, loadEvents: () => {
      calls++;
      return new Promise(resolve => { resolvePage = resolve; });
    } });
    const pending = controller.start();
    await controller.loadMore();
    assert.equal(calls, 1);
    resolvePage({ items: [], limit: 20, offset: 0, hasMore: false, total: 0 });
    await pending;
    await controller.loadMore();
    assert.equal(calls, 1);
    assert.deepEqual(view.items, []);
    assert.equal(view.pagination.hasMore, false);
  });

  it("ne demande pas d'offset au-dela de la limite de l'API", async () => {
    const view = viewStub();
    const controller = new EventsController({ view, loadEvents: async () => ({
      items: [event({ id: 1 })], limit: 20, offset: 10000, hasMore: true, total: 20000,
    }) });
    await controller.start();
    assert.equal(view.pagination.limitReached, true);
    assert.equal(controller.hasMore, false);
  });
});

describe("service de liste", () => {
  it("transmet limit/offset/from, normalise la page et conserve le format attendu par l'accueil", async () => {
    const previousFetch = globalThis.fetch;
    const urls = [];
    globalThis.fetch = async (url) => {
      urls.push(new URL(String(url)));
      return new Response(JSON.stringify({ data: { items: [{ ...eventDto(), creator: creatorDto() }], limit: 2, offset: 4, has_more: true, total: 7 } }),
        { status: 200, headers: { "content-type": "application/json" } });
    };
    try {
      const page = await fetchUpcomingEventPage({ limit: 2, offset: 4, from: "2026-11-08T09:00:00+01:00" });
      assert.equal(urls[0].searchParams.get("limit"), "2");
      assert.equal(urls[0].searchParams.get("offset"), "4");
      assert.equal(urls[0].searchParams.get("from"), "2026-11-08T09:00:00+01:00");
      assert.equal(page.hasMore, true);
      assert.equal(page.items[0].remainingPlaces, 238);
      assert.equal(page.offset, 4);
      assert.equal(page.total, 7);
      assert.equal(page.items[0].creator.displayName, "Garage ISEP");
      assert.ok(Array.isArray(await fetchUpcomingEvents({ limit: 100 })));
      assert.equal(urls[1].searchParams.get("offset"), "0");
    } finally { globalThis.fetch = previousFetch; }
  });
});

describe("service de detail", () => {
  it("charge le bon identifiant et normalise son createur", async () => {
    const previousFetch = globalThis.fetch;
    let path;
    globalThis.fetch = async (url) => {
      path = new URL(String(url)).pathname;
      return new Response(JSON.stringify({ data: { ...eventDto({ id: 42 }), creator: {
        id: 2, display_name: "Garage ISEP", description: null, avatar_url: null, school_name: "ISEP",
      } } }), { status: 200, headers: { "content-type": "application/json" } });
    };
    try {
      const detail = await fetchEvent(42);
      assert.equal(path, "/events/42");
      assert.equal(detail.id, 42);
      assert.equal(detail.creator.displayName, "Garage ISEP");
    } finally { globalThis.fetch = previousFetch; }
  });

  it("transmet l'erreur 404 de l'API pour un evenement inaccessible", async () => {
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => new Response(JSON.stringify({ error: { code: "EVENT_NOT_FOUND", message: "Event not found" } }),
      { status: 404, headers: { "content-type": "application/json" } });
    try { await assert.rejects(fetchEvent(9999), error => error instanceof ApiError && error.status === 404); }
    finally { globalThis.fetch = previousFetch; }
  });
});
