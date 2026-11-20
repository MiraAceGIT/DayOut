import { EventDetailController } from "../controller/EventDetailController.js";

/** Detail d'un event (route `/event?id=…`, voir app/appRoutes.js) ; un `id` absent ou repete donne la page 404.
 * @param {{ signal: AbortSignal, params: URLSearchParams }} page */
export function mountEvent({ signal, params }) {
  const ids = params.getAll("id");
  return new EventDetailController().start({ id: ids.length === 1 ? ids[0] : null, signal });
}
