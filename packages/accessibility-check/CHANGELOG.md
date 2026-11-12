# Changelog

All notable changes to `phil/accessibility-check` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `AccessibilityChecker`: `checkHtml()` checks a whole page or a fragment of HTML, `checkUjo()` a UJO (Universal JSON
  Output) document, with the rules the policy keeps, in the order they were given; `DefaultRules::all()` when none are;
  two rules of one identifier refused (`InvalidPolicy`).
- `Html\HtmlTarget`: HTML parsed by `Dom\HTMLDocument`, refused before parsing when larger than `maxBytes` or not
  UTF-8, its elements indexed once without recursion, in document order, by name and by identifier, refused when nested
  deeper than `maxDepth` or more than `maxElements`; a whole page told from a fragment; the line, element path and
  opening tag of an element; its language, whether it is hidden, its inline style, its role, its accessible name.
- `Ujo\UjoTarget` and `UjoReader`: the blocks of a document in document order, nested blocks after their parent,
  whether normalized or not, with their pointer and their language from the `language` tune; each text of the data
  that holds HTML parsed apart and located in its block and field, but the plain fields of `Ujo\PlainFields`; blocks
  counted against `maxElements`, nesting against `maxDepth`.
- Rules, each a class with an identifier, the WCAG 2.2 success criterion it tests and a severity: `image-alt`,
  `heading-order`, `heading-h1`, `empty-heading`, `link-name`, `button-name`, `link-text`, `color-contrast`,
  `table-headers`, `html-lang`, `lang-change`, `frame-title`, `video-captions`, `duplicate-id`, `svg-name`;
  `Contract\Rule`, `HtmlRule` and `UjoRule`, and `Rule\FragmentRule` for a rule that reads both.
- `Colour\Colour` and `ColourParser`: the colours of CSS — hexadecimal, `rgb()`, `hsl()`, `oklch()`, the named colours
  of CSS Color 4, `transparent`, opacity —, laid over one another, with the relative luminance and the contrast ratio of
  WCAG; `var()` and `color-mix()` told apart by `isUnresolved()` and reported by `color-contrast` as a notice,
  `color-contrast.unresolved`.
- `Language\LanguageTag`: well-formed language tags of BCP 47.
- `Rule\FileName`: a text alternative that is a file name, the image's own one included, read from the last segment of
  its address by PHP's WHATWG URL parser, as a browser reads it: relative or absolute, a backslash taken for a slash,
  query, fragment and user information left out, escapes decoded.
- `Policy`: limits, `HeadingPolicy`, `ContrastLevel`, default foreground and background, colours of the tokens of the
  look, language of a content, generic link texts by language — English, French, Spanish, German, Italian, Portuguese —,
  rules turned off or given another severity, locale of the messages.
- `Report`: the `RuleResult` of each rule and the `Finding`s — rule, `SuccessCriterion`, `Severity`, `Message`,
  `Location` —, filtered by severity and rule, translated, serializable to JSON.
- `SuccessCriterion`: the 86 success criteria of WCAG 2.2 with their title, `ConformanceLevel` and page of the W3C.
- `Translation\Messages`: the messages of the rules in English, French, Spanish, German, Italian and Portuguese for
  `symfony/translation`, in the domain `phil_accessibility_check`, a locale read with its fallbacks, the translator of
  the checker and the report replaceable by the application's.
- `Validator\AccessibleContent` and `AccessibleContentValidator`: a `symfony/validator` constraint on HTML or a UJO
  `Document`, each finding at or above `minSeverity` a violation, `INACCESSIBLE_ERROR`, a refused content one
  violation, `REFUSED_ERROR`.
- `Workflow\PublicationGuard` and `Contract\SubjectContent`: a guard of `symfony/workflow` that blocks a transition,
  such as `workflow.phil_content.guard.publish`, when the content of its subject has a finding at or above `blockAt`,
  with the report in the blocker's parameters, or is refused.
- `Console\CheckCommand`: `phil:accessibility:check`, a file or the HTML given as argument, HTML or UJO, in text or
  JSON, failing at a minimum severity; never an address.
- `AccessibilityCheckException`, implemented by `InvalidPolicy`, `LimitExceeded` and `InvalidContent`.
- The fuzz target `html-checker` and the benchmark of a document of 50 pages.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
