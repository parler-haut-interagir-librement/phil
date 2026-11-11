# Changelog

All notable changes to `phil/menu-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `MenuItem`: `map()` passes on an item whose children come back the same as it is, and checks the keys of the
  children only when one of them is renamed; `withUrl()` refuses an address with a scheme other than `http`,
  `https`, `mailto` and `tel`, such as `javascript:`, and one with a space or a control character;
  `withAttribute()` refuses a name that is not an attribute name or that starts with `on`, as event handlers do.
- `Menu`, `InvalidMenu`; `MenuBuilder`, `MenuDecorator`, `MenuContext`; `Menus`, which parses an address once to
  tell the current item.
- `PhilMenuBundle`, extension alias `phil_menu`.
- `MenuExtension`, which takes the translator and the URL generator, wired from the `translator` and `router`
  services when the application has them, and builds and decorates a menu once per request and name.
- `BreadcrumbSchema` and the Twig function `menu_breadcrumb_schema`: the breadcrumb as a schema.org
  `BreadcrumbList`, each item named by its translated label, an item with a route given its absolute URL, and an
  address on the site the request's scheme and host.
- The templates `menu.html.twig`, whose ancestors of the current item get `data-active-trail`, `breadcrumb.html.twig`
  and `cards.html.twig`; their labels in English and French.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
