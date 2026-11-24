# Reference

| Type or file | Role |
|---|---|
| `PhilSurveyBundle` | The bundle, its configuration `phil_survey` and its compiler passes |
| `Domain\ValueObject\Questionnaire`, `Section`, `Question`, `Choice` | A questionnaire and its parts, checked when built |
| `Domain\ValueObject\Ending`, `Quota` | An end screen, its profile and redirect; a quota and its condition |
| `Domain\ValueObject\Response`, `Addendum`, `RecordOutcome` | A stored response, an addendum, what recording a response did |
| `Domain\Status\QuestionType`, `QuestionnaireKind`, `Anonymity`, `Pacing` | The types of question, the kinds of questionnaire, anonymity and pace |
| `Domain\Logic\QuestionnaireLogic` | The conditions and computed variables, in `phil/form-logic`; the ending and the quotas of an answer |
| `Domain\Logic\AnswerCheck` | The checks of numbers, marks, choices, rankings and matrix rows |
| `Domain\Model\Read` | The strict reader of the questionnaire's data |
| `Domain\Contract\QuestionnaireRepository`, `ResponseRepository` | The ports of the store |
| `Domain\Exception\SurveyException`, `InvalidQuestionnaire`, `InvalidResponse` | Every exception of the bundle; a rule of the model broken; the errors of a response, by question |
| `Application\Questionnaires`, `QuestionnaireJson` | Saving, finding, exporting, importing and making from a template; the format `phil-survey/1` |
| `Application\Submissions`, `Receipt`, `Participations` | Taking a response; what the respondent gets back; the keyed marks of participation |
| `Application\RedirectPolicy` | The origins an ending may redirect to |
| `Application\Results`, `ResultsReport`, `QuestionResult` | The results counted by `phil/poll`, withheld below the minimum |
| `Application\Addenda` | Adding to a response by its token |
| `Application\Contract\FeedbackSessions`, `NoSessions` | The port of the sessions' windows, and its default with none |
| `Application\Contract\QuestionnaireTemplates`, `TemplateChain`, `BuiltInTemplates` | The port of the templates, the chain of the providers, the `session_feedback` template |
| `Application\NoRespondent` | The respondent when the application has no signed-in member |
| `Application\Message\PurgeSurveyResponses`, `Handler\PurgeSurveyResponsesHandler` | The purge of the responses past their retention |
| `Application\Exception\SurveyRefused`, `UnknownQuestionnaire` | A refusal and its reason; a key no questionnaire has |
| `Infrastructure\Doctrine\SurveyTables`, `SurveySchemaListener` | The tables, and the listener that adds them to the ORM's schema |
| `Infrastructure\Doctrine\DbalQuestionnaireRepository`, `DbalResponseRepository`, `Sql`, `Column` | The store on Doctrine DBAL, its atomic counters |
| `Infrastructure\Http\SurveyController`, `SurveyGuard` | The pages, and their stateless CSRF check |
| `Infrastructure\Console\ExportQuestionnaireCommand`, `ImportQuestionnaireCommand` | `phil:survey:export`, `phil:survey:import` |
| `Infrastructure\Scheduler\SurveySchedule` | The schedule `phil_survey` |
| `Infrastructure\Privacy\SurveyPrivacy` | The module `survey` of `phil/privacy-bundle` |
| `Infrastructure\DependencyInjection\SurveyServices`, `RespondentPass`, `CsrfProtectionPass` | The services, the respondent's service, the check of the CSRF protection |
| `config/routes.php` | The routes of the pages |
| `schema/questionnaire.schema.json` | The JSON schema of `phil-survey/1` |
| `templates/`, `translations/` | The pages, in the domain `phil_survey`, English and French |
| `assets/` | The Stimulus controller `progress` |

## Tables

| Table | Holds |
|---|---|
| `phil_survey_questionnaires` | Each questionnaire's data, its retention apart, its last change |
| `phil_survey_responses` | The responses: identifier, questionnaire, subject, respondent when identified, ending, answers, computed variables, addendum digest, instant |
| `phil_survey_addenda` | The addenda of each response, in order |
| `phil_survey_quota_counts` | One atomic counter per questionnaire and quota |
| `phil_survey_participations` | The marks: questionnaire, subject and keyed digest of the respondent |

Every instant is a `datetime_utc_immutable` column. On MySQL and MariaDB, keys and identifiers use the `utf8mb4_bin`
collation, so they compare byte by byte as on PostgreSQL and SQLite.
