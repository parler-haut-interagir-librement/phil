# Changelog

All notable changes to `phil/menu-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-11

### Security

- `MenuItem::withUrl()` refuses an address with a backslash, which a browser reads as a slash: `/\evil.example` and
  `\\evil.example`, taken for paths of the site, led to evil.example. An address of the web, `http:`, `https:` or
  `//`, is read by `phil/url`: one with a user name or a password, `https://example.com@evil.example/`, whose label
  says example.com, or whose host is an IP address, is refused.

### Changed

- `Menus` reads the path of an address of the site and the visitor's path by `phil/url`, no longer by `parse_url()`:
  `/games/rules/../about`, `/games/%2e%2e/about` and `/jeux/thé` are current at `/games/about`, `/about` and
  `/jeux/th%C3%A9`, the pages a browser asks for.
- The package requires `phil/url` 0.1.2.

## 0.1.0-alpha - 2026-11-11

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
