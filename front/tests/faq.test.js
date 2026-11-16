import assert from "node:assert/strict";
import { describe, it } from "node:test";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { FaqController } = await import("../src/controller/FaqController.js");
const { fetchFaq } = await import("../src/model/services/faqService.js");
delete globalThis.window;

const entries = [{ id: 3, question: "Qui peut s'inscrire ?", answer: "Les étudiants." }];

function viewStub() {
  return {
    isReady: true, state: "idle", entries: null, busy: false, error: "",
    retry: () => {},
    bind(onRetry) { this.retry = onRetry; },
    showLoading() { this.state = "loading"; this.busy = true; this.error = ""; },
    endLoading() { this.busy = false; },
    showEntries(value) { this.entries = value; this.state = "success"; },
    showError(message) { this.error = message; this.state = "error"; },
  };
}

describe("FAQ : API", () => {
  it("charge la route publique et preserve l'ordre ainsi que le texte", async t => {
    const data = [...entries, { id: 1, question: "<img src=x onerror=alert(1)>", answer: "<b>Texte</b> & réponse" }];
    t.mock.method(globalThis, "fetch", async (url, options) => {
      assert.equal(new URL(url).pathname, "/faq");
      assert.equal(options.method, "GET");
      return Response.json({ data });
    });
    assert.deepEqual(await fetchFaq(), data);
  });

  it("accepte une liste vide mais refuse un contrat invalide", async t => {
    for (const data of [[], null, {}, [null], [{ id: "1", question: "Q", answer: "R" }], [{ id: 1, question: "Q" }]]) {
      const mock = t.mock.method(globalThis, "fetch", async () => Response.json({ data }));
      if (Array.isArray(data) && !data.length) assert.deepEqual(await fetchFaq(), []);
      else await assert.rejects(fetchFaq(), TypeError);
      mock.mock.restore();
    }
  });

  it("propage une erreur HTTP sans la confondre avec une liste vide", async t => {
    t.mock.method(globalThis, "fetch", async () => Response.json({ error: { message: "Unavailable" } }, { status: 503 }));
    await assert.rejects(fetchFaq(), { status: 503 });
  });
});

describe("FAQ : controleur", () => {
  it("affiche les donnees et termine le chargement, y compris pour une liste vide", async () => {
    for (const data of [entries, []]) {
      const view = viewStub();
      await new FaqController({ view, loadFaq: async () => data }).start();
      assert.deepEqual(view.entries, data);
      assert.equal(view.state, "success");
      assert.equal(view.busy, false);
    }
  });

  it("permet de reessayer apres une panne", async () => {
    const view = viewStub();
    let calls = 0;
    await new FaqController({ view, loadFaq: async () => {
      if (++calls === 1) throw new Error("offline");
      return entries;
    } }).start();
    assert.equal(view.state, "error");
    assert.ok(view.error);
    assert.equal(view.busy, false);
    await view.retry();
    assert.equal(view.state, "success");
    assert.equal(view.error, "");
    assert.deepEqual(view.entries, entries);
  });

  it("annule le chargement au depart de la page et ignore une reponse tardive", async () => {
    const view = viewStub();
    const leave = new AbortController();
    /** @type {(value: import("../src/model/services/faqService.js").FaqEntry[]) => void} */
    let resolve = () => {};
    const result = new Promise(done => { resolve = done; });
    let calls = 0;
    const controller = new FaqController({ view, signal: leave.signal, loadFaq: ({ signal }) => {
      calls++;
      assert.equal(signal, leave.signal);
      return result;
    } });
    const loading = controller.start();
    await controller.load();
    assert.equal(calls, 1);
    leave.abort();
    resolve(entries);
    await loading;
    assert.equal(view.entries, null);
    assert.equal(view.error, "");
    await controller.load();
    assert.equal(calls, 1);
  });
});
