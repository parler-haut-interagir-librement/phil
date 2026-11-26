# Changelog

All notable changes to `phil/ux-easyadmin-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Changed

- The served stylesheet carries the tokens of `phil/ux-css` 0.6: `--phil-neutral-15`, and the dark surface on
  it.
- Requires the latest minor of each dependency (`phil/ux-css` 0.5), and of each development requirement
  (`doctrine/doctrine-bundle` 3.4, `symfony/ux-twig-component` 3.8, `twig/extra-bundle` 3.31).

### Security

- Requires `easycorp/easyadmin-bundle` 5.10.4 or later, the release that fixes eight advisories of the back office this
  theme dresses: GHSA-rrpv-c5fp-ppxr, GHSA-x7m7-8grf-r6m2, GHSA-v773-mfpm-hqgq, GHSA-4jgw-cjw7-8j52,
  GHSA-jqfc-9phg-ghr7, GHSA-f7rw-7wx5-3c54, GHSA-rjcw-39g5-9hqr and GHSA-63v8-jq78-cmg3.

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
