import { boot } from "../app/boot.js";
import { home } from "./home.js";
import { events } from "./events.js";
import { placeholders } from "./placeholders.js";
import { notFound } from "./notFound.js";

boot({ routes: [home, events, ...placeholders], notFound });
