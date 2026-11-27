# phil/survey-bundle

**Run surveys that adapt to each answer: conditional sections, ranking and matrix questions, cross quotas, anonymous
by default, results shown above a threshold.**

phil/survey-bundle is a Symfony bundle for questionnaires. A questionnaire has **sections** and **questions** of twelve
types — free text, email, number, date, yes or no, single and multiple choice, ranking, scale, Net Promoter Score and
matrix — and **conditions** stored as JsonLogic structures, never as code, evaluated by `phil/form-logic` in the browser
and again on the server. Free answers are checked by `phil/forms-bundle`; choices, marks, rankings and matrix rows by
the rules of `phil/poll`, which also counts them.

The server judges every response: hidden questions are dropped, every error is given at once, and the first **ending**
whose condition holds is shown, with its profile, or a **redirect** to an origin the application allows. **Cross
quotas** are kept by atomic counters in the response's transaction, so concurrent respondents never overfill them.

Questionnaires are **anonymous by default**, and the page says so before the first question: no respondent, no IP
address, the day only. **Session feedback** opens in a window around each session; **addenda** let a respondent add to
a sent response; **results** are withheld below a minimum, and shown to respondents only when a questionnaire turns them
on. Each questionnaire sets how long its responses are kept, and a scheduled purge deletes the rest. Questionnaires are
made from **templates**, and exported and imported as JSON with a published schema; their questions also come from a
**CSV file** through `phil/import-wizard`, with a dry run that refuses each row the questionnaire would refuse, cell by
cell. The identified responses take part in the export and erasure of `phil/privacy-bundle`.

The pages work without JavaScript; a Stimulus controller shows a question or a section at a time, with the progress.
The tables, on Doctrine DBAL, are tested on SQLite, MySQL 8.4 and PostgreSQL 17.

## Status

| Part | State |
|---|---|
| The questionnaire model, its twelve question types and its JSON exchange format and schema | Implemented, tested and fuzzed |
| Conditions in structure, evaluated by `phil/form-logic`; endings, profiles and redirects to allowed origins | Implemented and tested |
| Responses judged on the server, cross quotas by atomic counters | Implemented and tested, on SQLite, MySQL and PostgreSQL with concurrent respondents |
| Anonymity by default, session feedback and its window, addenda | Implemented and tested |
| Results withheld below a minimum, public results after answering, off by default | Implemented and tested |
| A retention per questionnaire, its scheduled purge | Implemented and tested |
| Templates and the `session_feedback` template | Implemented and tested |
| The CSV import of the questions through `phil/import-wizard`, its dry run and its command | Implemented, tested and fuzzed |
| The export and the erasure of `phil/privacy-bundle` | Implemented and tested |
| The pages without JavaScript and the `progress` Stimulus controller | Implemented; the controller's pacing tested under Node |
| A visual builder of conditions, exports to spreadsheets | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Questionnaires](doc/questionnaires.md) | The model, the question types, the conditions, the JSON format and the templates. |
| [CSV import](doc/csv-import.md) | The CSV file of questions, its cells, the dry run, the import into a questionnaire and the command. |
| [Responses](doc/responses.md) | Submissions, endings and redirects, quotas, anonymity, session feedback and addenda. |
| [Results, retention and privacy](doc/results.md) | The results and their minimum, the purge, the export and the erasure. |
| [Pages](doc/pages.md) | The routes, the templates and the Stimulus controller. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\SurveyBundle\PhilSurveyBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_survey.yaml
phil_survey:
    redirect_origins: ['https://example.org']
```

```yaml
# config/routes/phil_survey.yaml
phil_survey:
    resource: '@PhilSurveyBundle/config/routes.php'
    prefix: /survey
```

```php
$questionnaires->fromTemplate('session_feedback', 'keynote', 'Feedback on the keynote');
// GET /survey/keynote?subject=keynote-2026 shows it while the session's feedback is open.
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
