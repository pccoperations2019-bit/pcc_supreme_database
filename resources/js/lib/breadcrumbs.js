export function buildEntityBreadcrumbs({
  sectionLabel,
  sectionHref,
  itemLabel,
  itemHref,
  actionLabel,
  actionHref,
}) {
  return [
    sectionLabel ? { name: sectionLabel, href: sectionHref } : null,
    itemLabel ? { name: itemLabel, href: itemHref } : null,
    actionLabel ? { name: actionLabel, href: actionHref } : null,
  ].filter(Boolean)
}
