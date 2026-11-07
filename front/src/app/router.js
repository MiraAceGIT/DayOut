import { parseHash } from "../core/routes.js";

function classesOf(value) {
  return (value ?? "").split(" ").filter(Boolean);
}

/**
 * Ne connait aucun ecran : chaque route est `{ id, path, title, template, mainClass, bodyClass, mount(), unmount() }`,
 * `template` etant l'id d'un <template> de index.html.
 */
export function startRouter({ routes, notFound, main, announcer = null, onNavigate = () => {} }) {
  if (!main) return;
  let current = null;

  function navigate({ initial }) {
    const target = parseHash(location.hash) ?? (initial ? parseHash("") : null);
    if (!target) return; // ancre interne (skip-link) : on reste sur l'ecran
    const key = `${target.path}?${target.params}`;
    if (current?.key === key) return;

    const route = routes.find((r) => r.path === target.path) ?? notFound;
    const template = document.getElementById(route.template);
    if (!(template instanceof HTMLTemplateElement)) {
      console.error(`Template "${route.template}" introuvable.`);
      return;
    }

    current?.route.unmount?.();
    main.replaceChildren(template.content.cloneNode(true));
    main.classList.remove(...classesOf(current?.route.mainClass));
    main.classList.add(...classesOf(route.mainClass));
    document.body.classList.remove(...classesOf(current?.route.bodyClass));
    document.body.classList.add(...classesOf(route.bodyClass));
    current = { route, key };

    route.mount?.({ params: target.params });

    document.title = `DayOut — ${route.title}`;
    if (!initial) {
      window.scrollTo(0, 0);
      const heading = main.querySelector("h1");
      if (heading) {
        if (!heading.hasAttribute("tabindex")) heading.setAttribute("tabindex", "-1");
        heading.focus();
      }
      if (announcer) announcer.textContent = route.title;
    }
    onNavigate(route);
  }

  window.addEventListener("hashchange", () => navigate({ initial: false }));
  navigate({ initial: true });
}
