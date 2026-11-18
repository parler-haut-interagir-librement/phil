<!-- phil-dev source-sha256=7a8532d14c2bf1efa406177a3ea4e6138aa033b63a7af7ba74cd7988cbfa7018 -->
# Référence

## Bundle

| Classe ou fichier | Rôle |
|---|---|
| `PhilCoursesBundle` | Le bundle : sa configuration `phil_courses` et le jeton CSRF sans état `phil_courses` |
| `config/routes.php` | Les routes des pages |
| `config/cmi5_routes.php` | L'URL de récupération et le point de terminaison xAPI |
| `config/content_routes.php` | Les fichiers des paquets importés et le cadre de l'environnement d'exécution SCORM, sur l'origine du contenu |
| `config/admin_routes.php` | La page d'administration des secondes chances |
| `templates/` | `layout`, `outline`, `activity` et `second_chances`, dessinés avec les recettes du kit |
| `translations/` | Les mots des pages, en anglais et en français |
| `assets/src/scorm_runtime_controller.js` | Le contrôleur Stimulus de la page d'activité d'une session SCORM : encadre le cadre, envoie les validations |
| `assets/src/scorm_frame.js`, `assets/src/lib/api.js`, `assets/src/lib/cmi.js` | Le cadre de l'environnement d'exécution sur l'origine du contenu, l'API d'exécution, et le modèle de données dans le navigateur |
| `recipe/0.1` | La recette Flex : le bundle, sa configuration et ses routes |

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\Course` | Un cours : titre, description, origine, source, ordre imposé |
| `Domain\Model\Module` | Un module : titre, place, ouverture |
| `Domain\Model\Activity` | Une activité : type, paramètres, exigence, obligatoire, essais, ouverture, source, bloc |
| `Domain\Model\Block` | Un bloc d'une structure de cours cmi5 : titre, place parmi les enfants de son parent, source, parent |
| `Domain\Model\Enrollment` | L'inscription d'un apprenant : enregistrement, moment, achèvement |
| `Domain\Model\Progress` | La progression d'un apprenant dans une activité |
| `Domain\Model\RetryRequest` | Une demande de seconde chance, sa décision et la note de l'enseignant |
| `Domain\Model\LaunchSession` | Un lancement cmi5 : récupéré ou non, expiration, état |
| `Domain\Model\ScormAttempt` | Une tentative SCORM : numéro, version, état, session, données de suivi, statut, temps total |
| `Domain\Scorm\AttemptState` | `running`, `paused`, `suspended`, `ended` |
| `Domain\ValueObject\ScormAttemptId` | L'identifiant d'une tentative SCORM |
| `Domain\ValueObject\CourseId`, `ModuleId`, `BlockId`, `ActivityId`, `EnrollmentId`, `RetryRequestId`, `LaunchSessionId` | Des identifiants, UUID version 7 quand le bundle les tire |
| `Domain\ValueObject\LearnerId` | L'identifiant stable d'un apprenant, jamais une adresse courriel |
| `Domain\ValueObject\Release` | Tout de suite, à un moment, ou un nombre de jours après l'inscription |
| `Domain\ValueObject\Score` | Un score normalisé, de -1 à 1 |
| `Domain\ValueObject\Outcome` | Ce qu'un apprenant a fait : terminé, réussi ou échoué, score |
| `Domain\ValueObject\Availability` | Où en est une activité pour un apprenant, et ce qui reste à faire |
| `Domain\Progress\CompletionRequirement` | Les valeurs du `moveOn` de cmi5 |
| `Domain\Progress\AvailabilityStatus` | `open`, `satisfied`, `scheduled`, `locked`, `retry_needed`, `retry_requested` |
| `Domain\Progress\RetryDecision` | `pending`, `granted`, `refused`, `waived` |
| `Domain\Course\CourseOrigin` | `native`, `scorm12`, `scorm2004`, `cmi5` ; `of()` le format d'un paquet |
| `Domain\Cmi5\Cmi5Verb`, `Cmi5Iri`, `SessionState` | Les verbes, les IRI et les états de session de cmi5 ; les modes de lancement sont le `LaunchMethod` de phil/elearning-packages |
| `Domain\Contract\CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`, `LaunchSessionRepository`, `ScormAttemptRepository` | Les entrepôts |
| `Domain\Event\ScormSessionEnded` | Une session d'une activité SCORM a pris fin, et si sa tentative aussi |
| `Domain\Exception\AttemptRefused` | Une validation refusée : son jeton, son apprenant ou sa session n'est pas celui de la tentative |
| `Domain\Event\*` | `LearnerEnrolled`, `ActivityProgressed`, `ActivitySatisfied`, `CourseCompleted`, `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived`, `BlockSatisfied`, `CourseImported`, `StatementRecorded` |
| `Domain\Exception\CoursesException` | Ce qu'est chaque échec du bundle |
| `Domain\Exception\InvalidCourse`, `UnknownRecord`, `ActivityUnavailable`, `PackageRefused`, `StatementRefused`, `RecordStoreUnavailable` | Une valeur refusée, un enregistrement manquant, une activité non ouverte, un paquet refusé, un énoncé refusé, un énoncé que l'entrepôt de traces d'apprentissage n'a pas conservé |
| `Domain\Identifiers` | La forme des identifiants et des titres |

## Application

| Classe | Rôle |
|---|---|
| `Application\Courses` | Écrit et lit les cours, les modules, les blocs et les activités |
| `Application\Enrollments` | Inscription, progression, dispenses, essais, achèvement |
| `Application\Completions`, `Completion` | Si un apprenant a terminé un cours, avec l'inscription et le moment |
| `Application\Availabilities` | Où en est chaque activité pour un apprenant |
| `Application\Learning` | Lance une activité et la marque terminée pour l'apprenant |
| `Application\SecondChances`, `RetryReview` | Secondes chances demandées, accordées, refusées ou dispensées, et leur revue |
| `Application\ActivityTypes` | Les types d'activité par nom |
| `Application\ActivityType\PageActivity`, `LinkActivity`, `ScormActivity`, `Cmi5Activity` | Les adaptateurs des types d'activité |
| `Application\Launch`, `LaunchWindow` | L'endroit où l'apprenant va pour suivre une activité |
| `Application\ContentUrls` | Les adresses des fichiers sur l'origine du contenu |
| `Application\CourseOutline`, `ModuleOutline`, `ActivityStatus` | Un cours lu comme un plan, et où en est une activité |
| `Application\Contract\ActivityType`, `CurrentLearner`, `ContentStorage`, `LearningRecordStore`, `Cmi5Endpoints` | Les ports ; les paquets sont ouverts par le `PackageOpener` de phil/elearning-packages |
| `Application\Import\CourseImporter` | Importe un paquet ou une structure de cours, lus par phil/elearning-packages |
| `Application\Import\ScormPlanner`, `Cmi5Planner`, `ImportPlan`, `PlannedModule`, `PlannedBlock`, `PlannedActivity` | Transforment un manifeste en cours à créer |
| `Application\Import\ImportReport` | Ce qu'un import a créé et laissé de côté |
| `Application\Import\ResultImporter`, `ScormStatus` | Les résultats importés |
| `Application\Scorm\ScormRuntime` | Ouvre les sessions des tentatives SCORM, conserve leurs validations, y met fin et enregistre leur résultat |
| `Application\Scorm\ScormTokens` | Les jetons de tentative |
| `Application\Scorm\ScormLaunch`, `CommitAnswer` | Ce qu'un lancement remet au navigateur, et ce qu'une validation répond |
| `Application\Cmi5\Cmi5Launcher` | Lance une unité |
| `Application\Cmi5\Cmi5Tokens` | La réponse de l'URL de récupération, et les jetons |
| `Application\Cmi5\Cmi5Statements` | Reçoit et émet des énoncés |
| `Application\Cmi5\StatementRules` | Les règles de cmi5 |
| `Application\Cmi5\PlatformStatements`, `LaunchData`, `Cmi5Context`, `Cmi5Settings` | Les énoncés de la plateforme, les données de lancement, le contexte d'un lancement, les paramètres |
| `Application\Cmi5\IssueWaivedStatement` | Émet l'énoncé `waived` d'une unité dispensée, puis les énoncés `satisfied` qu'elle entraîne |
| `Application\Cmi5\Cmi5Satisfaction` | Émet les énoncés `satisfied` des blocs et du cours, une seule fois pour chaque inscription ; interne, atteint par `Cmi5Statements::satisfy()` |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\CourseTables`, `SchemaListener` | Les tables, leur mise à niveau, et leur place dans le schéma de l'ORM |
| `Infrastructure\Doctrine\DbalCourseRepository`, `DbalEnrollmentRepository`, `DbalRetryRequestRepository`, `DbalLaunchSessionRepository`, `DbalLearningRecordStore`, `Rows` | Les entrepôts DBAL |
| `Infrastructure\Certificates\CertificatesCourseCompletions` | L'adaptateur du port `CourseCompletions` de phil/certificates-bundle |
| `Infrastructure\Xapi\HttpLearningRecordStore` | L'entrepôt de traces d'apprentissage xAPI distant, par phil/safe-http, pour les énoncés ; les documents d'état restent dans `DbalLearningRecordStore` |
| `Infrastructure\Storage\FilesystemContentStorage` | Le stockage sur le système de fichiers |
| `Infrastructure\Http\CourseController`, `CourseRequest` | Les pages et leur vérification CSRF |
| `Infrastructure\Http\Cmi5Controller` | L'URL de récupération et le point de terminaison xAPI |
| `Infrastructure\Http\ContentController` | Les fichiers sur l'origine du contenu |
| `Infrastructure\Http\ScormController`, `ScormCommitPayload` | Le point de terminaison de validation des sessions SCORM, et le corps qu'il lit |
| `Infrastructure\Http\ScormFrameController` | Le cadre de l'environnement d'exécution SCORM, sur l'origine du contenu |
| `Infrastructure\Doctrine\DbalScormAttemptRepository` | Les tentatives SCORM par DBAL |
| `Infrastructure\Privacy\ScormPrivacy` | Les tentatives SCORM dans les demandes d'accès et d'effacement de phil/privacy-bundle |
| `Infrastructure\Http\SecondChancesController` | La page d'administration des secondes chances |
| `Infrastructure\Routing\RoutedCmi5Endpoints` | Les URL du point de terminaison et de l'URL de récupération |
| `Infrastructure\Console\ImportCommand`, `ImportResultsCommand`, `RetriesCommand` | Les commandes |
| `Infrastructure\Audit\AuditSecondChances` | La piste des secondes chances |
| `Infrastructure\Security\SecurityCurrentLearner` | L'apprenant tiré de Symfony Security |
| `Infrastructure\Twig\CoursesExtension`, `KitComponents` | `phil_courses_kit()` et les noms des recettes |
| `Infrastructure\DependencyInjection\CoursesServices`, `KitSubdirectoryPass` | Les services |

## Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `phil:courses:import <path> [--title=]` | Importe un paquet SCORM ou cmi5, ou une structure de cours cmi5 |
| `phil:courses:import-results <course> <file>` | Importe une exportation CSV de résultats |
| `phil:courses:retries [--grant=] [--refuse=] [--waive=] [--by=] [--note=]` | Énumère les secondes chances, ou en accorde, en refuse ou en dispense une avec la note de l'enseignant |
