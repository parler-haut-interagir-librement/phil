# Changelog

All notable changes to `phil/markup-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.3-alpha - 2026-11-18

The next version is 0.1.3-alpha: three optional keys, on `phil/markup` 0.3.

### Added

- The `max_bytes`, `ujo_max_bytes` and `purifier_max_bytes` configuration: the ceiling of a source compiled or read in
  a written format, 1 MiB by default, of a UJO source, 4 MiB, and of an HTML purified, 4 MiB — the defaults of
  `phil/markup` —, each a positive integer checked when the container is built. They reach `DefaultCompiler` and every
  purifier built by `defaultPolicies()`, also when another bundle registers the same service after this one: the
  compiler pass `MaxBytesPass` gives the compiler its ceilings back, and `MediaHostsPass` the purifier its ceiling with
  its hosts.
- The section [Ceilings](doc/configuration.md#ceilings) of the configuration page.

### Changed

- `phil/markup` 0.3 is required: its readers, its compiler and its purifiers refuse what is longer than their ceiling.

## 0.1.2-alpha - 2026-11-15

### Added

- The `purifier` configuration: `auto`, the default, for `Purifiers::defaultPolicies()` of `phil/markup` 0.2 — the
  `ammonia` extension of PHP where it is loaded, `symfony/html-sanitizer` otherwise —, `sanitizer` or `ammonia`, the
  last refused where the extension is not loaded; the service `phil_markup.purifier`, aliased by `HtmlPurifier`.

### Changed

- `MediaHostsPass` gives the `media_hosts` to `AmmoniaPurifier` and `Purifiers` too, and points `HtmlPurifier` back
  at the purifier chosen when a later bundle points it at `SanitizerPurifier`.
- `phil/markup` ^0.2.1 and `phil/ujo` ^0.1.2 are required: `Purifiers` and `AmmoniaPurifier`, bold and italic of
  Markdown and the wikis as `<strong>` and `<em>`, and the accessible checkboxes of checklists.

## 0.1.1-alpha - 2026-11-14

### Changed

- `phil/markup` 0.2 accepted as well as 0.1.

## 0.1.0-alpha - 2026-11-11

### Added

- `MarkupFormats`: the enabled formats by name, the default one, the labels of the formats of add-ons, and the level of
  a text lowered to its format's ceiling;
  `UnavailableFormat`, a format not enabled, a `MarkupException` of `phil/markup`.
- `UjoConverter`: the reader of every enabled format, and a source read into a UJO document, its metadata and its
  JSON, at a level lowered to the format's ceiling.
- `PhilMarkupBundle` and the `phil_markup` configuration: `formats`, the enabled formats in the order a form offers
  them; `default_format`; `default_level`; `levels`, the highest level each format is purified at; `added_formats`,
  the formats add-ons bring — their label and the service of their `ReaderProvider`, given to `DefaultCompiler` —,
  which an add-on bundle prepends; `media_hosts`, the hosts images may come from.
- `MarkupServices`: the UJO renderer of `phil/ujo`; `DefaultCompiler` rendering with it and `SanitizerPurifier`,
  aliased by `MarkupCompiler` and `HtmlPurifier`; `Markup`, `MarkupFormats` and `UjoConverter`, public.
- `MediaHostsPass`: the `media_hosts` given to `SanitizerPurifier` once every bundle is loaded, so that a bundle
  registering the same adapter keeps them.
- `SmartPunctuationCompiler` and `SmartPunctuationPass`: smart punctuation in every format when `phil/typography`
  is installed — the HTML of the compiler through a typographer, before the purifier —, in the configured locale or
  that of Symfony's `LocaleSwitcher`, with the typographers of `phil/typography-bundle` when it registers them, and the
  formats it already fixes through the UJO renderer left to it; the `smart_punctuation` configuration, to switch it
  off or fix its locale.
- `MarkupFormatType`: a choice of an enabled format, by name, the formats of add-ons with their label, registered when Symfony Form is installed, its labels in the
  `phil_markup` translation domain, in English and French.
- `MarkupExtension`: the `markup_html`, `markup_purify` and `markup_ujo` Twig filters, registered when Twig is installed.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
