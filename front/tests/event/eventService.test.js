import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { eventDto } from "../fixtures.js";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { ApiError } = await import("../../src/core/api.js");
const { fetchEvent, fetchUpcomingEventPage } = await import("../../src/model/services/eventService.js");
const { toEventQuery } = await import("../../src/model/mappers/event/eventRequestMapper.js");
delete globalThis.window;

const pageDto = (overrides = {}) => ({ items: [eventDto()], limit: 20, offset: 0, has_more: false, total: 1, ...overrides });

describe("requete GET /events", () => {
  it("encode la pagination et la date avec fuseau", () => {
    assert.deepEqual(Object.fromEntries(toEventQuery({ limit: 50, offset: 10, from: "2026-11-10T12:00:00+01:00" })), {
      limit: "50", offset: "10", from: "2026-11-10T12:00:00+01:00",
    });
  });

  it("borne la taille des pages et rejette les offsets invalides", () => {
    /** @type {[number, string][]} */
    const cases = [[0, "1"], [-10, "1"], [2.9, "2"], [999, "100"]];
    for (const [input, expected] of cases) assert.equal(toEventQuery({ limit: input }).get("limit"), expected);
    for (const offset of [-1, 1.1, 10001, NaN, Infinity]) assert.throws(() => toEventQuery({ offset }), RangeError);
    for (const limit of [NaN, Infinity]) assert.throws(() => toEventQuery({ limit }), RangeError);
  });

  it("ajoute le mot-cle nettoye et coupe a la longueur acceptee par l'API", () => {
    assert.equal(toEventQuery({ keyword: "  jazz  " }).get("q"), "jazz");
    assert.equal(toEventQuery({ keyword: "   " }).has("q"), false);
    assert.equal(toEventQuery({ keyword: "a".repeat(150) }).get("q")?.length, 100);
  });
});

describe("service evenement", () => {
  it("appelle /events avec la requete et le signal, et convertit la page", async t => {
    const { signal } = new AbortController();
    t.mock.method(globalThis, "fetch", async (url, options) => {
      const requested = new URL(url);
      assert.equal(requested.pathname, "/events");
      assert.deepEqual(Object.fromEntries(requested.searchParams), { limit: "50", offset: "10" });
      assert.equal(options.signal?.aborted, false);
      return Response.json({ data: pageDto({ limit: 50, offset: 10, total: 11 }) });
    });
    const page = await fetchUpcomingEventPage({ limit: 50, offset: 10, signal });
    assert.equal(page.items[0].creator.displayName, "Garage ISEP");
    assert.equal(page.total, 11);
    assert.equal(page.hasMore, false);
  });

  it("n'appelle pas l'API avec une pagination invalide", async t => {
    const fetchMock = t.mock.method(globalThis, "fetch", async () => Response.json({ data: pageDto() }));
    await assert.rejects(fetchUpcomingEventPage({ offset: -1 }), RangeError);
    assert.equal(fetchMock.mock.callCount(), 0);
  });

  it("laisse l'API valider l'identifiant, encode dans un seul segment du chemin", async t => {
    const paths = [];
    t.mock.method(globalThis, "fetch", async url => {
      paths.push(new URL(url).pathname);
      return Response.json({ data: eventDto() });
    });
    for (const id of ["9223372036854775807", "../faq", "1?x=2", 42]) await fetchEvent(id);
    assert.deepEqual(paths, ["/events/9223372036854775807", "/events/..%2Ffaq", "/events/1%3Fx%3D2", "/events/42"]);
  });

  it("une reponse 404 de l'API (identifiant invalide ou inconnu) devient une ApiError 404", async t => {
    t.mock.method(globalThis, "fetch", async () => Response.json(
      { error: { code: "NOT_FOUND", message: "Event not found" } }, { status: 404 }));
    await assert.rejects(fetchEvent("abc"), error => error instanceof ApiError && error.status === 404);
  });

  it("preserve les erreurs HTTP et leur diagnostic", async t => {
    for (const status of [404, 422, 500]) {
      const mock = t.mock.method(globalThis, "fetch", async () => Response.json(
        { error: { code: "TEST_ERROR", message: "Failure", details: { field: "q" } } },
        { status, headers: { "X-Request-Id": "req-42" } },
      ));
      for (const call of [() => fetchEvent(1), () => fetchUpcomingEventPage()]) {
        await assert.rejects(call(), error => error instanceof ApiError
          && error.status === status && error.code === "TEST_ERROR" && error.requestId === "req-42");
      }
      mock.mock.restore();
    }
  });
});
