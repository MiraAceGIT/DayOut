export function isAppUrl(url, origin) {
  return url.origin === origin && !url.pathname.includes(".");
}

export function linkNavigation(click, link, current) {
  if (click.defaultPrevented || click.button !== 0) return null;
  if (click.ctrlKey || click.metaKey || click.shiftKey || click.altKey) return null;
  if (link.target || link.download) return null;
  const url = new URL(link.href);
  if (!isAppUrl(url, current.origin)) return null;
  if (url.hash && url.pathname === current.pathname && url.search === current.search) return null;
  return url;
}

export function formNavigation(form, fields, current) {
  if (form.method.toLowerCase() !== "get" || form.target) return null;
  const url = new URL(form.action);
  if (!isAppUrl(url, current.origin)) return null;
  url.search = new URLSearchParams([...fields]).toString();
  url.hash = "";
  return url;
}

export function withParam(href, name, value) {
  const url = new URL(href);
  if (value.trim()) url.searchParams.set(name, value);
  else url.searchParams.delete(name);
  return url;
}
