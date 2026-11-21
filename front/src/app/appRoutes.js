import { mountHome } from "../pages/home.js";
import { mountEvents } from "../pages/event/events.js";
import { mountEvent } from "../pages/event/event.js";
import { mountFaq } from "../pages/faq.js";

export const ROUTES = [
  { path: "/faq", file: "pages/faq.html", mount: mountFaq },
  { path: "/",       file: "pages/home.html",   mount: mountHome },
  { path: "/events",    file: "pages/event/events.html", mount: mountEvents },
  { path: "/event",     file: "pages/event/event.html",  mount: mountEvent },
];

export const NOT_FOUND = { file: "pages/not-found.html" };
