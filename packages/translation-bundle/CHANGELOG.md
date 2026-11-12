# Changelog

All notable changes to `phil/translation-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Domain\Model\SegmentRecord`, `MemoryRecord` and `TermRecord`: the rows of the tables `phil_translation_segment`,
  `phil_translation_memory` and `phil_translation_term`, keyed by document, target language and segment key; by
  languages and source fingerprint; by languages and term. A segment row keeps who wrote and reviewed its target,
  whether a machine proposed it, and the flags of its quality checks; a term row keeps the inflected forms of the term
  and of its translations. `InvalidRecord` for a document reference, a term, a person or flags out of bounds, or a row
  given another segment or term.
- `Domain\Model\CommentRecord`: the comments on segments, in the table `phil_translation_comment`, 1 to 4000
  characters, signed by an identifier.
- `Domain\Repository\SegmentRepository`, `TermRepository` and `Contract\CommentRepository`, the ports of the stores.
- `Application\SourceDocuments`, `SourceDocument` and `TranslationPolicy` — who may translate, review and edit
  glossaries —, the application's ports behind the screen; `NotFound` for a segment, a document, a term or a comment
  the stores do not hold.
- `Application\Contract\Translators`: the identifier of the current user and the public credit of each translator.
- `Application\Contract\MachineTranslator` and `MachineTranslationFailed`: machine translation behind adapters named
  after their service, off by default.
- `Application\Translations`: a document prepared — cut, synchronized, new segments proposed the exact matches of the
  memory —, or its preparation previewed and exported as XLIFF without storing anything; a segment translated, proposed,
  reviewed, confirmed or cleared, each signed by the current user, usable targets learnt by the memory; matches,
  glossary terms and issues of a segment; the quality checks of a segment, dismissed by flags kept with its row;
  progress; XLIFF export and import; machine translations proposed by batches of 50 and marked as machine output; the
  public credit of a document's translators; the translated document, read through the chain of a regional variant —
  `fr-CA`, `fr`, the source —, and its raw reading from the language's own segments.
- `Translations::import()` merges a unit marked `final` or `signed-off` as reviewed only with `canReview: true`, which
  the screen passes from `TranslationPolicy::canReview()`; by default, it merges it as translated.
- With a `TranslationPolicy`, the memory proposes and prefills only the units learnt from a document the current user
  may translate, or from no document.
- The screen answers a failure of the machine translator with a message of its own, never what the service answered.
- `Application\Glossaries`: terms put and removed, the glossary of a pair of languages with the forms of an inflector,
  TBX export and import.
- `Application\Comments` and `Credit`: comments added, listed by segment and removed; a person thanked and their
  counts.
- `Application\Progress`: the segments of a document counted by state, the usable share.
- Doctrine stores of segments, terms and memory — the memory looked up by fingerprint and by length —, and stores in
  memory.
- The configuration `phil_translation`: store, entity manager, granularity, prefill, minimum score, number of
  suggestions, the application's services, the layout, the regional variants, the translators, the inflector and
  machine translation; the Doctrine mapping of the rows, the screen's CSRF token
  among the stateless ones of FrameworkBundle and its Stimulus controller for AssetMapper prepended;
  `CsrfProtectionPass`, which stops the container with a message when `framework.csrf_protection` is false.
- `Infrastructure\Http\TranslationController` and its routes: the page of a document in a target language, a form per
  segment, XLIFF export and import with a report; the page and the export, `GET` requests, store nothing; forms from
  another site refused by `SameOriginGuard`, on the request's origin and Symfony's stateless CSRF check of the token
  `phil_translation`; the Twig templates `@PhilTranslation/translate.html.twig` and a layout of their own. The page shows
  the quality checks with a button that dismisses each, the target a regional variant inherits, the mark of machine
  output and the comments of each segment; routes to comment and to ask for machine translations.
- `Infrastructure\Http\GlossaryController` and its routes: the glossary of a pair of languages edited on screen
  through `Glossaries`, exported and imported as TBX; `@PhilTranslation/glossary.html.twig`.
- `Infrastructure\Twig\CreditsExtension` and `@PhilTranslation/credits.html.twig`: the Twig function
  `phil_translation_credits()` and the public credit of a document's translators.
- `Infrastructure\EasyAdmin\TranslationAdmin`: the menu links of an EasyAdmin back office to the glossary pages.
- The `csrf` Stimulus controller: a new CSRF token in each form of the screen as it is sent, in its field and in a
  cookie; the module of the double-submitted token and its tests under Node's test runner.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
