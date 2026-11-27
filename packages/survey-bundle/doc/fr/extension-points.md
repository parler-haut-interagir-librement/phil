<!-- phil-dev source-sha256=b164360ed457824f60902e8c11eebf871909abcfc7b35e0b176333e2ebef9629 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Comment |
|---|---|
| Les séances de la rétroaction de séance | Implémentez `Application\Contract\FeedbackSessions` et nommez le service dans `phil_survey.sessions` |
| Le répondant | Nommez un service `CurrentSubject` de `phil/forms-bundle` dans `phil_survey.respondent` ; sinon, le membre de Symfony Security |
| Les gabarits de questionnaires | Implémentez `Application\Contract\QuestionnaireTemplates` ; configuré automatiquement, ou étiquetez-le `phil_survey.template_provider`. Le premier fournisseur qui connaît un nom donne son gabarit |
| Le stockage | Faites un alias de `Domain\Contract\QuestionnaireRepository` ou de `ResponseRepository` vers un service de l'application |
| Le décompte des résultats | Définissez de nouveau le service `Application\Results` avec un `CountingMethods` de `phil/poll` |
| Les pages | Désactivez-les avec `phil_survey.pages: false` et appelez `Submissions`, `Results` et `Addenda` depuis des contrôleurs de l'application |
| Les gabarits Twig | Remplacez-les sous `templates/bundles/PhilSurveyBundle/` |
| Les textes | Traduisez le domaine `phil_survey` |
| L'échange de questionnaires | `Questionnaires::export()` et `import()`, ou le schéma JSON dans `schema/` |
| L'importation CSV des questions | `QuestionImports`, ou `QuestionCsvProfile::profile()` avec un `QuestionImportTarget` donné au planificateur et à l'importateur de `phil/import-wizard` ; une autre source de lignes implémente son `RowSource` |

Le bundle ne distribue aucun événement. Une réponse est enregistrée en une seule transaction ; une application qui y
réagit lit le `Receipt` que renvoie `Submissions::submit()`.
