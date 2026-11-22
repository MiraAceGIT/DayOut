import { mountHome } from "../pages/home.js";
import { mountEvents } from "../pages/event/events.js";
import { mountEvent } from "../pages/event/event.js";
import { mountFaq } from "../pages/faq.js";
import { mountRegister } from "../pages/user/register.js";
import { mountConfirmEmail } from "../pages/user/confirmEmail.js";

export const ROUTES = [
  { path: "/faq", file: "pages/faq.html", mount: mountFaq },
  { path: "/",       file: "pages/home.html",   mount: mountHome },
  { path: "/events",    file: "pages/event/events.html", mount: mountEvents },
  { path: "/event",     file: "pages/event/event.html",  mount: mountEvent },
  { path: "/register",      file: "pages/user/register.html",      mount: mountRegister },
  { path: "/confirm-email", file: "pages/user/confirm-email.html", mount: mountConfirmEmail },
];

export const NOT_FOUND = { file: "pages/not-found.html" };
