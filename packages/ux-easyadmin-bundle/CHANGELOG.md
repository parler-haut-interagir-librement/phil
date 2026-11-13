# Changelog

All notable changes to `phil/ux-easyadmin-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PhilUxEasyAdminBundle`, extension alias `phil_ux_easy_admin`, and its key `theme`.
- The stylesheet `assets/styles/phil-ux-easyadmin.css`, mapped as `phil-ux-easyadmin/` with AssetMapper: the tokens of
  phil/ux-css under EasyAdmin's `vendor` layer, and the layer `phil-easyadmin`, declared after `ea`, giving
  EasyAdmin's variables — accent, surfaces, sidebar, text, links, borders, tables, forms, alerts, badges, corners,
  focus width and fonts — the PHIL UX roles, light and dark.
- `Infrastructure\EasyAdmin\ThemeStylesheet`, which links the stylesheet on every page of every dashboard, after the
  dashboard's own stylesheets and once.
- `Infrastructure\Stylesheet\ThemeSheet` and `bin/build.php`, which write the stylesheet from
  `assets/src/phil-ux-easyadmin.css` and the token files of phil/ux-css.
- The tests that hold the theme to EasyAdmin 5.10 and phil/ux-css 0.5: every variable it sets declared by EasyAdmin,
  every token it reads declared by phil/ux-css, and its colours computed by Chrome in both schemes.
- The Flex recipe `recipe/0.1/`, which registers the bundle and writes its configuration.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
