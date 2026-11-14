import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { eventDto } from "./fixtures.js";

// La configuration partagee reste celle du navigateur ; seul le transport est injecte.
globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { ApiError } = await import("../src/core/api.js");
const { createEventService } = await import("../src/model/services/eventService.js");
delete globalThis.window;

const pageDto = (overrides = {}) => ({ items: [eventDto()], limit: 20, offset: 0, has_more: false, total: 1, ...overrides });

describe("contrat HTTP du service evenement", () => {
  it("encode la pagination et la date avec fuseau, et transmet le signal hors de l'URL", async () => {
    const { signal } = new AbortController();
    /** @type {{ url: URL, options: { signal?: AbortSignal } }} */
    let requested;
    const service = createEventService({ get: async (path, options) => {
      requested = { url: new URL(path, "https://api.example"), options };
      return pageDto({ limit: 50, offset: 10, total: 11 });
    } });
    const page = await service.fetchUpcomingEventPage({
      limit: 50, offset: 10, from: "2026-11-10T12:00:00+01:00", signal,
    });
    assert.equal(requested.url.pathname, "/events");
    assert.deepEqual(Object.fromEntries(requested.url.searchParams), {
      limit: "50", offset: "10", from: "2026-11-10T12:00:00+01:00",
    });
    assert.equal(requested.options.signal, signal);
    assert.equal(page.items[0].creator.displayName, "Garage ISEP");
    assert.equal(page.total, 11);
    assert.equal(page.hasMore, false);
  });

  it("borne la taille des pages et rejette les offsets invalides", async () => {
    const paths = [];
    const service = createEventService({ get: async path => {
      paths.push(path);
      return pageDto({ limit: Number(new URL(path, "https://api.example").searchParams.get("limit")) });
    } });
    for (const [input, expected] of [[0, 1], [-10, 1], [2.9, 2], [999, 100]]) {
      await service.fetchUpcomingEventPage({ limit: input });
      assert.equal(paths.at(-1), `/events?limit=${expected}&offset=0`);
    }
    for (const offset of [-1, 1.1, 10001, NaN, Infinity]) {
      await assert.rejects(service.fetchUpcomingEventPage({ offset }), RangeError);
    }
    for (const limit of [NaN, Infinity]) await assert.rejects(service.fetchUpcomingEventPage({ limit }), RangeError);
    assert.equal(paths.length, 4, "aucun appel avec une pagination invalide");
  });

  it("refuse une reponse invalide au lieu d'afficher une liste vide ou de boucler sur Voir plus", async () => {
    const invalid = [null, "<html>Erreur proxy</html>", {}, [],
      pageDto({ items: {} }), pageDto({ items: [null] }), pageDto({ items: [eventDto({ creator: null })] }),
      pageDto({ has_more: "false" }), pageDto({ total: null }), pageDto({ limit: 0 }),
      pageDto({ offset: -1 }), pageDto({ items: [], has_more: true }),
      pageDto({ offset: 10001 }), pageDto({ limit: 101 }), pageDto({ total: 2 }),
      pageDto({ has_more: true }), pageDto({ items: [eventDto(), eventDto()], total: 2 }),
    ];
    for (const dto of invalid) {
      const service = createEventService({ get: async () => dto });
      await assert.rejects(service.fetchUpcomingEventPage(), error => error instanceof ApiError && error.type === "parse");
    }
    const service = createEventService({ get: async () => pageDto({ items: [], total: 0 }) });
    assert.deepEqual((await service.fetchUpcomingEventPage()).items, []);
    const beyondEnd = createEventService({ get: async () => pageDto({ items: [], total: 2, offset: 20 }) });
    assert.deepEqual((await beyondEnd.fetchUpcomingEventPage({ offset: 20 })).items, []);
  });

  it("valide les donnees critiques du detail avant tout rendu", async () => {
    for (const dto of [null, {}, "OK", eventDto({ id: 0 }), eventDto({ start_at: "invalid" }),
      eventDto({ is_paid: undefined }), eventDto({ price_cents: -1 }), eventDto({ categories: [null] }),
      eventDto({ creator: { id: 2 } }), eventDto({ capacity: "100" }), eventDto({ status: "other" })]) {
      const service = createEventService({ get: async () => dto });
      await assert.rejects(service.fetchEvent(1), error => error instanceof ApiError && error.type === "parse");
    }
  });

  it("valide l'identifiant avant le reseau et conserve les grands identifiants en chaine", async () => {
    const calls = [];
    const notFound = new ApiError("Not found", { status: 404 });
    const service = createEventService({ get: async (path, options) => { calls.push({ path, options }); throw notFound; } });
    for (const id of ["", "01", "../events", "1?x=2", 0, -1, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
      await assert.rejects(service.fetchEvent(id), error => error instanceof ApiError && error.status === 404);
    }
    assert.equal(calls.length, 0);
    const { signal } = new AbortController();
    await assert.rejects(service.fetchEvent("9223372036854775807", { signal }), error => error === notFound);
    assert.equal(calls[0].path, "/events/9223372036854775807");
    assert.equal(calls[0].options.signal, signal);
  });

  it("refuse le detail d'un autre evenement meme si son DTO est valide", async () => {
    const service = createEventService({ get: async () => eventDto({ id: 2 }) });
    await assert.rejects(service.fetchEvent(1), error => error instanceof ApiError && error.type === "parse");
  });

  it("ne masque pas un bug de conversion en erreur de reponse serveur", async () => {
    const bug = new TypeError("Mapper bug");
    const dto = eventDto();
    Object.defineProperty(dto, "latitude", { get() { throw bug; } });
    const service = createEventService({ get: async () => dto });
    await assert.rejects(service.fetchEvent(1), error => error === bug);
  });

  it("preserve les erreurs HTTP et leur diagnostic sans les transformer en erreurs de contrat", async () => {
    for (const status of [404, 422, 500]) {
      const error = new ApiError("Failure", { status, code: "TEST_ERROR", details: { field: "q" }, requestId: "req-42" });
      const service = createEventService({ get: async () => { throw error; } });
      await assert.rejects(service.fetchEvent(1), actual => actual === error);
      await assert.rejects(service.fetchUpcomingEventPage(), actual => actual === error);
    }
  });
});
