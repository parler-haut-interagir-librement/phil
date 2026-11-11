# Changelog

All notable changes to `phil/css-sanitizer` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `CssSanitizer`: `sanitize()` for style sheets and `sanitizeDeclarations()` for `style` attributes, returning
  `Sanitized` — the CSS, the kept rules as `Sheet\StyleSheet`, `Sheet\ImportRule`, `Sheet\StyleRule` and
  `Sheet\GroupRule`, the kept declarations — and each `Removal` with its `RemovalReason`, subject and line, the first
  `Sanitized::MAX_REMOVALS` (100) listed and the others counted in `omittedRemovals`.
- `Policy`: allow lists of properties, functions, pseudo-classes, pseudo-elements, attributes, media types and at-rules
  with defaults for screen and print, a scope selector, `!important` and custom properties, limits of length, depth
  and nesting levels; the properties and functions that run code or load a resource can never be allowed
  (`InvalidPolicy`).
- `UrlRewriter` and `UrlKind`, with `Url\RefuseUrls`, `Url\AllowedFiles` and `Url\CallbackRewriter`: every URL —
  images, fonts, the style sheets of `@import` — rewritten or refused, fragments kept without asking, `javascript:`,
  `vbscript:`, `livescript:` and `mocha:` refused before and after the rewriter.
- Selectors checked against the grammar of Selectors Level 4 — type, universal, class, ID and attribute selectors,
  pseudo-classes with their arguments, pseudo-elements, combinators — and the policy's names; nested selectors
  relative to their parent, with `&` refused inside `:not()`, `:is()` and `:where()` so that it cannot leave the
  scope.
- Values checked token by token: allowed functions with their arguments, `url()` and each image of `image-set()`
  through the rewriter, `type()` beside them, `attr()` with the type `url` only inside `target-counter()`,
  `target-counters()` and `target-text()`.
- At-rules: `@media` with the policy's media types and features that load nothing, `@supports` without URLs, `@page`
  with its page selectors, its descriptors and its sixteen margin boxes, `@font-face` with its descriptors and its
  local or rewritten sources; `@import` at the start of a sheet, when the policy allows it, rewritten to an allowed
  style sheet with its `supports()` and media conditions; every other at-rule refused.
- CSS Nesting: style rules, `@media` and `@supports` nested in a style rule, read as a block's contents, their levels
  bounded by the policy (`RemovalReason::Nesting`), declarations written before nested rules.
- `Syntax\Tokenizer`, the tokenizer of CSS Syntax Level 3 written from the specification: the input preprocessed and
  made valid UTF-8, every token with its escapes resolved, comments dropped, bad strings and bad URLs, the line of each
  token.
- `Syntax\Parser`, the parser of CSS Syntax Level 3: component values, simple blocks and functions, lists of rules and
  of declarations, a block's contents as CSS Nesting reads them, `!important`, what the specification drops kept as
  `Malformed`, nesting bounded by `NestingTooDeep`; `Token`, `SimpleBlock`, `FunctionValue`, `QualifiedRule`, `AtRule`,
  `Declaration` and `Stylesheet`.
- `Serializer`: component values, declarations and sanitized style sheets written back as CSS, identifiers and strings
  escaped as CSSOM does, URLs as `url("…")`, an empty comment where two tokens would join, `<` escaped or kept apart
  from `/` so that the output never starts an end tag.
- `CssSanitizerException`, implemented by `InvalidPolicy` and `NestingTooDeep`.
- `Validator\SafeCss` and `Validator\SafeCssValidator`, a constraint for `symfony/validator`: each removal a violation,
  and the removals past the first 100 one more, `moreMessage`, with their count.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
