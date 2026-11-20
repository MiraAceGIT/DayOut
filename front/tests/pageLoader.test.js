import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { PageLoader } from "../src/controller/PageLoader.js";

function viewStub() {
  return {
    calls: /** @type {string[]} */ ([]),
    showLoading() { this.calls.push("loading"); },
    endLoading() { this.calls.push("end"); },
    showError(/** @type {string} */ message) { this.calls.push(`error:${message}`); },
  };
}

describe("PageLoader", () => {
  it("affiche le resultat, ou le message d'une erreur d'API, puis termine le chargement", async () => {
    const view = viewStub();
    const loader = new PageLoader(view);
    /** @type {unknown[]} */
    const shown = [];
    await loader.run(async () => 42, { onSuccess: (value) => shown.push(value) });
    assert.deepEqual(shown, [42]);
    assert.deepEqual(view.calls, ["loading", "end"]);

    view.calls = [];
    const apiError = Object.assign(new Error("down"), { name: "ApiError", type: "network", status: 0 });
    await loader.run(async () => { throw apiError; }, { onSuccess: () => assert.fail("pas de succes") });
    assert.equal(view.calls.length, 3);
    assert.match(view.calls[1], /^error:Impossible de joindre le serveur/);
    assert.equal(loader.busy, false);
  });

  it("laisse onError traiter une erreur attendue", async () => {
    const view = viewStub();
    let handled = null;
    await new PageLoader(view).run(async () => { throw new Error("404"); }, {
      onSuccess: () => {},
      onError: (error) => { handled = error; return true; },
    });
    assert.ok(handled);
    assert.deepEqual(view.calls, ["loading", "end"]);
  });

  it("ignore un second appel pendant un chargement", async () => {
    const loader = new PageLoader(viewStub());
    let calls = 0;
    /** @type {() => void} */
    let finish = () => {};
    const first = loader.run(() => { calls++; return new Promise((done) => { finish = () => done(undefined); }); }, { onSuccess: () => {} });
    await loader.run(async () => { calls++; }, { onSuccess: () => {} });
    finish();
    await first;
    assert.equal(calls, 1);
  });

  it("ne touche plus la view une fois la page quittee, meme si la reponse arrive ensuite", async () => {
    const view = viewStub();
    const page = new AbortController();
    const loader = new PageLoader(view, { signal: page.signal });
    /** @type {(value: number) => void} */
    let respond = () => {};
    let shown = false;
    const pending = loader.run((signal) => {
      assert.equal(signal, page.signal, "le signal de la page est transmis a la requete");
      return new Promise((done) => { respond = done; });
    }, { onSuccess: () => { shown = true; } });
    page.abort();
    respond(1);
    await pending;
    assert.equal(shown, false);
    assert.deepEqual(view.calls, ["loading"]);

    await loader.run(async () => 1, { onSuccess: () => { shown = true; } });
    assert.equal(shown, false, "plus de chargement apres le depart");
  });
});
