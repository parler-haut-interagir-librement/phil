# Changelog

All notable changes to `phil/highlight` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Token` and `TokenType`: a piece of the source and what it is, 26 types whose HTML class is `hl-` and their value.
- `Language`, the interface of a tokenizer that never throws and never loses a byte.
- `Code`, the tokenizer of a programming language described by a `Syntax` — words, comments, `Quote`s, prefixes,
  sigils, annotations, symbols, macros, preprocessor lines, regular expressions —, and `Definitions`, the syntaxes of
  PHP, JavaScript, TypeScript, JSON, Python, Ruby, Go, Rust, Java, C, C++, C#, SQL, shell and Twig expressions.
- `Markup` (HTML with its scripts and styles, XML), `StyleSheet` (CSS, SCSS), `Yaml`, `Ini`, `Dockerfile`, `Diff`,
  `Markdown`, `Twig` and `Php` (code and markup mixed).
- `HtmlFormatter`, the tokens as escaped HTML in `<span class="hl-…">`, at once or line by line with `formatLines()`.
- `LineOptions`, `LineRanges` and `LineMark`: a block line by line, each line in `<span class="hl-line">`, numbered
  from a first number, the lines of ranges such as `1,3-5` highlighted, and the `[!code ++]`, `[!code --]` and
  `[!code highlight]` annotations of comments, with a number of lines after `:`, read and taken out.
- `Highlighter`: every shipped language by name and alias in any case, languages added or replaced, tokens, HTML,
  line by line given `LineOptions`, and `highlight()` in the shape a code renderer takes.
- `HighlightException`, implemented by `UnknownLanguage`, `InvalidLanguage` and `InvalidLineRanges`.
- The themes `themes/light.css`, `themes/dark.css` and `themes/auto.css`, marked lines told by a tint, a bar and a
  sign.
- The fuzz targets `line-ranges` and `line-annotations`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
