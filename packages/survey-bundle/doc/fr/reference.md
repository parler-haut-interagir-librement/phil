<!-- phil-dev source-sha256=6ad192a374b3d24f756888a3b87041e573420195fb3707bce4648d962841c6d5 -->
# Référence

| Type ou fichier | Rôle |
|---|---|
| `PhilSurveyBundle` | Le bundle, sa configuration `phil_survey` et ses passes de compilation |
| `Domain\ValueObject\Questionnaire`, `Section`, `Question`, `Choice` | Un questionnaire et ses parties, vérifiés à la construction |
| `Domain\ValueObject\Ending`, `Quota` | Un écran de fin, son profil et sa redirection ; un quota et sa condition |
| `Domain\ValueObject\Response`, `Addendum`, `RecordOutcome` | Une réponse stockée, un addendum, ce qu'a fait l'enregistrement d'une réponse |
| `Domain\Status\QuestionType`, `QuestionnaireKind`, `Anonymity`, `Pacing` | Les types de questions, les genres de questionnaires, l'anonymat et le rythme |
| `Domain\Logic\QuestionnaireLogic` | Les conditions et les variables calculées, dans `phil/form-logic` ; la fin et les quotas d'une réponse |
| `Domain\Logic\AnswerCheck` | Les vérifications des nombres, des notes, des choix, des classements et des lignes de matrice |
| `Domain\Model\Read` | Le lecteur strict des données du questionnaire |
| `Domain\Contract\QuestionnaireRepository`, `ResponseRepository` | Les ports du stockage |
| `Domain\Exception\SurveyException`, `InvalidQuestionnaire`, `InvalidResponse` | Toutes les exceptions du bundle ; une règle du modèle enfreinte ; les erreurs d'une réponse, par question |
| `Application\Questionnaires`, `QuestionnaireJson` | Enregistrer, trouver, exporter, importer et créer à partir d'un gabarit ; le format `phil-survey/1` |
| `Application\Submissions`, `Receipt`, `Participations` | Recevoir une réponse ; ce que le répondant reçoit en retour ; les marques de participation à clé |
| `Application\RedirectPolicy` | Les origines vers lesquelles une fin peut rediriger |
| `Application\Results`, `ResultsReport`, `QuestionResult` | Les résultats comptés par `phil/poll`, retenus sous le minimum |
| `Application\Addenda` | Compléter une réponse avec son jeton |
| `Application\Contract\FeedbackSessions`, `NoSessions` | Le port des fenêtres de séance, et sa valeur par défaut sans séance |
| `Application\Contract\QuestionnaireTemplates`, `TemplateChain`, `BuiltInTemplates` | Le port des gabarits, la chaîne des fournisseurs, le gabarit `session_feedback` |
| `Application\NoRespondent` | Le répondant quand l'application n'a aucun membre connecté |
| `Application\Message\PurgeSurveyResponses`, `Handler\PurgeSurveyResponsesHandler` | La purge des réponses échues |
| `Application\Exception\SurveyRefused`, `UnknownQuestionnaire` | Un refus et sa raison ; une clé qu'aucun questionnaire n'a |
| `Infrastructure\Doctrine\SurveyTables`, `SurveySchemaListener` | Les tables, et l'écouteur qui les ajoute au schéma de l'ORM |
| `Infrastructure\Doctrine\DbalQuestionnaireRepository`, `DbalResponseRepository`, `Sql`, `Column` | Le stockage sur Doctrine DBAL, ses compteurs atomiques |
| `Infrastructure\Http\SurveyController`, `SurveyGuard` | Les pages, et leur vérification CSRF sans état |
| `Infrastructure\Console\ExportQuestionnaireCommand`, `ImportQuestionnaireCommand` | `phil:survey:export`, `phil:survey:import` |
| `Infrastructure\Scheduler\SurveySchedule` | Le calendrier `phil_survey` |
| `Infrastructure\Privacy\SurveyPrivacy` | Le module `survey` de `phil/privacy-bundle` |
| `Infrastructure\DependencyInjection\SurveyServices`, `RespondentPass`, `CsrfProtectionPass` | Les services, le service du répondant, la vérification de la protection CSRF |
| `config/routes.php` | Les routes des pages |
| `schema/questionnaire.schema.json` | Le schéma JSON de `phil-survey/1` |
| `templates/`, `translations/` | Les pages, dans le domaine `phil_survey`, en anglais et en français |
| `assets/` | Le contrôleur Stimulus `progress` |

## Tables

| Table | Contenu |
|---|---|
| `phil_survey_questionnaires` | Les données de chaque questionnaire, sa conservation à part, son dernier changement |
| `phil_survey_responses` | Les réponses : identifiant, questionnaire, sujet, répondant s'il est identifié, fin, réponses, variables calculées, empreinte de l'addendum, instant |
| `phil_survey_addenda` | Les addenda de chaque réponse, dans l'ordre |
| `phil_survey_quota_counts` | Un compteur atomique par questionnaire et par quota |
| `phil_survey_participations` | Les marques : questionnaire, sujet et empreinte à clé du répondant |

Chaque instant est une colonne `datetime_utc_immutable`. Sur MySQL et MariaDB, les clés et les identifiants emploient
le classement `utf8mb4_bin` : ils se comparent donc octet par octet, comme sur PostgreSQL et SQLite.
