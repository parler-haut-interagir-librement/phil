# Changelog

All notable changes to `phil/survey-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Added

- The CSV import of the questions of a questionnaire through `phil/import-wizard`: `QuestionCsvProfile`, one row per
  question by its identifier, with its type, label, help, choices and rows written `value = label` or by position,
  bounds, section and the usual English and French headers, a file of 1 MiB, 300 rows and 32 cells at most;
  `QuestionRowRule`, which refuses in the dry run each row the questionnaire would refuse, with the cell at fault;
  `QuestionImportTarget`, which replaces a question in place with its condition kept, moves it to the section a row
  names, adds the new ones, creates a questionnaire that does not exist, and refuses only the rows the questionnaire
  refuses as a whole; `QuestionImports`, the public service of the dry run and the import.
- The command `phil:survey:import-questions`: the dry run line by line, and the import with `--write`.
- The fuzz target `survey-question-csv`, and the documentation of the import in `doc/csv-import.md`.

## 0.1.1-alpha - 2026-11-25

### Security

- The marks of participation were the HMAC under `secret` itself, `%kernel.secret%` by default, which other bundles
  keyed their own hashes with. `Participations` derives its key by HKDF-SHA256 under `phil_survey.participation`
  (`Participations::USAGE`). The marks kept change: a member who answered before the update can answer once more, and a
  request about them no longer finds their earlier marks.

## 0.1.0-alpha - 2026-11-24

### Added

- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The Flex recipe, in `recipe/0.1`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- The questionnaire model: sections, questions of twelve types — text, long text, email, number, date, yes or no,
  single and multiple choice, ranking, scale, Net Promoter Score and matrix —, computed variables, endings and quotas,
  each checked when built; free answers checked by `phil/forms-bundle`, choices, marks, rankings and matrix rows by the
  rules of `phil/poll`.
- Conditions stored as JsonLogic structures and evaluated by `phil/form-logic`: a question's and a section's
  condition read the questions before them, typed; endings and quotas read every question, each matrix row and every
  computed variable.
- The JSON exchange format `phil-survey/1`, bounded and strict, with its JSON schema in `schema/`, and the commands
  `phil:survey:export` and `phil:survey:import`.
- `Submissions`: the server judges every response, drops the hidden questions, gives every error at once, chooses the
  first ending whose condition holds, and records the response with the respondent's mark and the quotas' counts in one
  transaction.
- Endings with a profile, and redirects checked by `phil/url` against the origins the application allows, when saved
  and when used; never an open redirect.
- Cross quotas kept by atomic counters — a conditional update in the transaction —, tested with concurrent processes
  on MySQL and PostgreSQL; a full quota screens the respondent out and keeps nothing.
- Anonymous responses by default: no respondent, no IP address, a UUID v4 identifier and the day only as their instant;
  the marks of participation keep a keyed digest, with no time and no reference to a response.
- Session feedback, open from a number of minutes before the session to a number of minutes after it, the sessions
  given by the `FeedbackSessions` port.
- Addenda added after sending, by a token only the respondent holds, its SHA-256 kept.
- `Results`: the counts of `phil/poll` from the stored answers, withheld below the questionnaire's minimum, whole or
  question by question; results shown to respondents after answering, off by default.
- A retention per questionnaire, and the purge `PurgeSurveyResponses`, idempotent, on the schedule `phil_survey` with
  a lock and a state.
- Templates: the `QuestionnaireTemplates` port, the providers tagged `phil_survey.template_provider`, and the
  `session_feedback` template.
- The responses that name a person, and their marks, in the export and the erasure of `phil/privacy-bundle`.
- The pages: the questionnaire without JavaScript, its anonymity said before the first question, the ending, the
  results and the addendum, in English and French; the Stimulus controller `progress` shows a question or a section at
  a time with the progress, beside the `logic` controller of `phil/form-logic`.
- The documentation in `doc/`: installation, configuration, questionnaires, responses, results with retention and
  privacy, pages, extension points, testing and reference, indexed by the README.
