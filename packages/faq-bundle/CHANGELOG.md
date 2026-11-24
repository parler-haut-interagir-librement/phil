# Changelog

All notable changes to `phil/faq-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `Domain\Model\Section` and `SectionTranslation`: a section, its key — its anchor — kept when its title changes, its
  place, and its title per language; `Question` and `QuestionTranslation`: a question, its anchor made once and kept,
  its place in its section, its totals of useful and not useful answers, and per language its wording, its answer as
  written and as purified HTML, its publication and the moment it changed, in UTC; `UnansweredSearch`, a total per
  language and normalized search with its latest day; `SpentVoteToken`, the hash of a token used for a question. The
  tables are prefixed `phil_faq_`.
- `Domain\ValueObject\Locale` — `fr-CA` read as `fr_CA`, its language as fallback, its BCP 47 tag — and `VoteCount`;
  the ports `FaqRepository`, `UnansweredSearchRepository` and `SpentVoteTokenRepository`; `InvalidFaq`, and
  `FaqException` for everything the bundle throws.
- `Application\FaqEditor`: sections and questions added, titled, written in each language, published, placed before
  another or at the end, removed; keys and anchors made from the first title or wording and numbered when taken;
  answers in Markdown, HTML or text read by `phil/markup` and purified at its `forum` level.
- `Application\FaqPages`: the page of a language, each question in its locale or its language, published only, the
  sections without a question left out, at most `max_questions`; the search kept to the questions holding every word,
  accents and case aside, through `QueryNormalizer`, with the views `FaqPage`, `SectionView` and `QuestionView`.
- `Application\HelpfulVotes` and `VoteTokens`: a vote token per page, 16 random bytes and an expiry signed with
  HMAC-SHA-256 under the application's secret, that votes once per question; the votes held back per question through
  the `Throttle` port; `AlreadyVoted`, `VoteRefused`, `TooManyRequests`, `UnknownQuestion` and `UnknownSection`.
- `Application\UnansweredSearches`: a search that finds nothing counted, normalized, per language and per day in UTC,
  from three characters, held back per language.
- `Application\FaqStructuredData`: the `FAQPage` JSON-LD of the questions shown, in the language of the page, through
  `phil/structured-data`; `FaqMarkdown`: the same questions as Markdown for an `llms.txt` file.
- `Application\FaqTransfer`: every section and question as plain data, and back, matched by key and anchor, the shape
  checked first and the changes made in one transaction.
- `Infrastructure\Doctrine\DoctrineFaqRepository`, `DbalUnansweredSearches` and `DbalSpentVoteTokens`: the store in
  the Doctrine ORM, a vote counted by one `UPDATE`, a total started or added to whatever comes at the same time, a token
  spent once by its primary key, the expired ones forgotten.
- `Infrastructure\Twig\Components\FaqComponent`, the Twig component `PhilFaq`: the search form, the sections, each
  question a `details` element whose `id` is its anchor with its share link and copy button, the vote form, the link to
  the contact form after a search without answer, and the JSON-LD; drawn with the recipes `Button`, `Input` and
  `CopyButton` of the PHIL UX kit, under the subdirectory of the Toolkit.
- `Infrastructure\Http\FaqController`, `FaqGuard` and `config/routes.php`: the page, filtered by its `q` and counting a
  search without answer; the vote, from a plain form that comes back to a path of the site or from the controller in
  JSON; the count of a search without answer; Symfony's stateless CSRF check of the token `phil_faq`.
- `assets/src/faq_controller.js`, with `lib/search.js` and `lib/voted.js`: the search as one types, folded as the
  server folds it, a search still without answer after a pause counted once, the question of the address opened, the
  votes sent without leaving the page and remembered in the browser's own storage; `assets/package.json` for Symfony
  Flex and StimulusBundle.
- `Infrastructure\Http\FaqAdminController`, `AdminGuard` and `config/admin_routes.php`: the administration behind a
  role, in the configured languages — sections and questions, their state and votes, the forms, the buttons that move,
  publish and delete, the totals of votes and of searches without answer —; `Infrastructure\EasyAdmin\FaqAdmin`, the
  links of an EasyAdmin dashboard.
- `Infrastructure\Console\ExportCommand` and `ImportCommand`: `phil:faq:export` and `phil:faq:import`, in YAML.
- `PhilFaqBundle`, its configuration `phil_faq`, the token `phil_faq` among the stateless ones, the limiters
  `phil_faq_votes` and `phil_faq_searches`, the Doctrine mapping and the AssetMapper path; `CsrfProtectionPass` and
  `KitSubdirectoryPass`; the templates and their English and French texts; the Flex recipe.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
