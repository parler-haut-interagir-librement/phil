# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | How |
|---|---|
| The sessions of session feedback | Implement `Application\Contract\FeedbackSessions` and name the service in `phil_survey.sessions` |
| The respondent | Name a `CurrentSubject` service of `phil/forms-bundle` in `phil_survey.respondent`; the member of Symfony Security otherwise |
| Templates of questionnaires | Implement `Application\Contract\QuestionnaireTemplates`; autoconfigured, or tag it `phil_survey.template_provider`. The first provider that knows a name gives its template |
| The store | Alias `Domain\Contract\QuestionnaireRepository` or `ResponseRepository` to a service of the application |
| The counting of results | Define the service `Application\Results` again with a `CountingMethods` of `phil/poll` |
| The pages | Turn them off with `phil_survey.pages: false` and call `Submissions`, `Results` and `Addenda` from controllers of the application |
| The templates | Override them under `templates/bundles/PhilSurveyBundle/` |
| The texts | Translate the domain `phil_survey` |
| The exchange of questionnaires | `Questionnaires::export()` and `import()`, or the JSON schema in `schema/` |
| The CSV import of questions | `QuestionImports`, or `QuestionCsvProfile::profile()` with a `QuestionImportTarget` given to the planner and the importer of `phil/import-wizard`; another source of rows implements its `RowSource` |

The bundle dispatches no event. A response is recorded in one transaction; an application that reacts to it reads the
`Receipt` that `Submissions::submit()` returns.
