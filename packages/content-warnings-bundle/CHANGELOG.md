# Changelog

All notable changes to `phil/content-warnings-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilContentWarningsBundle`, extension alias `phil_content_warnings`, with the keys `base_catalogue`,
  `catalogue_files`, `catalogue`, `default_display`, `fallback_locales` and `preferences_store`; the catalogue is
  built when the container is compiled, so a definition that breaks a rule stops the build.
- `CatalogueFactory`, `CatalogueLabels` and `WarningScreen`, which build the catalogue, label it in the current
  locale and decide for the current reader.
- `Contract\WarningPreferencesStore`, `SessionWarningPreferences`, which never starts a session to read and gives
  the defaults for preferences it cannot read back, `InMemoryWarningPreferences`, reset between requests, and
  `Exception\PreferencesNotSaved`.
- `ContentWarningsColumnType`, the column type `phil_content_warnings`.
- `ContentWarningsType` and `ContentWarningsTransformer`, which keep the severity and note of a warning kept and the
  warnings of themes the catalogue no longer has; the form theme `@PhilContentWarnings/form/theme.html.twig`, a
  fieldset per category; `WarningPreferencesType` and `WarningPreferencesTransformer`, which keep what the form does
  not list and the severity of a display kept.
- `ContentWarningsExtension`: `content_warnings_decision`, `content_warning_label`, `content_warning_description`,
  `content_warning_category`; the template `@PhilContentWarnings/fold.html.twig`.
- The Stimulus controller `reveal` and the stylesheet `assets/styles/content-warnings.css`, its fade-in only without
  `prefers-reduced-motion: reduce`.
- The labels and messages in English, French, Spanish, German, Italian and Portuguese, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
