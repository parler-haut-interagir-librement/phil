# Changelog

All notable changes to `phil/highlight-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- The `Highlighter` service: the languages of `phil/highlight`, the services implementing `Language`, autoconfigured
  with the `phil_highlight.language` tag, each in place of a language of the same name, and the configured aliases.
- `PhilHighlightBundle` and the `phil_highlight` configuration: `aliases`, more names for a language, checked as the
  highlighter checks them; and the `ujo` integration, which can be switched off.
- `HighlighterPass` and `AliasedLanguage`: the tagged languages and the aliases added once every bundle is loaded,
  and the `HtmlRenderer` of `phil/ujo-bundle` decorated with the highlighter, so the code blocks of every UJO document
  are coloured on the server.
- The Twig filter `highlight`: code escaped and wrapped in `hl-` spans in a language, or escaped alone for a language
  the highlighter does not know or none; with `lines`, line by line, numbered, ranges highlighted and the annotations
  of comments read.
- `assets/styles/highlight.css`: a theme of the `hl-` classes and of the blocks that hold them, light and dark by the
  system or `data-theme`, light on paper, its colours custom properties meeting the WCAG AA contrast, marked lines told
  by a tint, a bar and a sign, mapped for AssetMapper as `phil-highlight/highlight.css`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
