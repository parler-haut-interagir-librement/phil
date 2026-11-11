# Changelog

All notable changes to `phil/markup-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
