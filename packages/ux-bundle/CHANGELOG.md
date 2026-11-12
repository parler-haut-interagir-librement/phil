# Changelog

All notable changes to `phil/ux-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `PhilUxBundle`, extension alias `phil_ux`.
- The PHIL UX form theme `@PhilUx/form/phil_ux_layout.html.twig`: Symfony's div layout with its rows written as the
  fields of the PHIL UX kit (`isField`, `isFieldSet` and its legend for compound fields, `isFieldDescription`,
  `isFieldError`, `isRadioGroup`), prepended to `twig.form_themes` so that every form renders with it and the
  application's themes win; `phil_ux.form_theme: false` turns it off.
- The `dist/` directory of phil/ux-css mapped as `phil-ux/` with AssetMapper, served as `phil-ux/phil-ux.css`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
