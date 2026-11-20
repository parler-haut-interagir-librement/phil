<!-- phil-dev source-sha256=3668f147744f660a064cdbd403f1956c815d7676a0c3c581772806904dc2a559 -->
# Référence

## Bundle

| Classe ou fichier | Rôle |
|---|---|
| `PhilCoursesBundle` | Le bundle : sa configuration `phil_courses` et le jeton CSRF sans état `phil_courses` |
| `config/routes.php` | Les routes des pages |
| `config/cmi5_routes.php` | L'URL de récupération et le point de terminaison xAPI |
| `config/content_routes.php` | Les fichiers des paquets importés et le cadre de l'environnement d'exécution SCORM, sur l'origine du contenu |
| `config/admin_routes.php` | Les pages d'administration : les secondes chances, la formation en retard ou bientôt due, les relevés |
| `templates/` | `layout`, `outline`, `activity`, `second_chances`, `training`, `training_admin` et `transcript`, dessinés avec les recettes du kit |
| `translations/` | Les mots des pages, et les notifications de la formation obligatoire dans le domaine `phil_notifications`, en anglais et en français |
| `assets/src/scorm_runtime_controller.js` | Le contrôleur Stimulus de la page d'activité d'une session SCORM : encadre le cadre, envoie les validations |
| `assets/src/scorm_frame.js`, `assets/src/lib/api.js`, `assets/src/lib/cmi.js` | Le cadre de l'environnement d'exécution sur l'origine du contenu, l'API d'exécution, et le modèle de données dans le navigateur |
| `recipe/0.2` | La recette Flex : le bundle, sa configuration et ses routes (`recipe/0.1` pour les versions 0.1) |

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
| `Domain\Model\Assignment` | Un cours assigné à un apprenant, un groupe ou une unité : échéance, renouvellement, qui l'a assigné |
| `Domain\Model\Obligation` | La part d'un apprenant dans une assignation : cycle, échéance, achèvement, renouvellement, rappels envoyés |
| `Domain\Model\TranscriptEntry` | Un achèvement dans le relevé d'un apprenant : le cours et son titre, le moment, les crédits, l'assignation et le cycle |
| `Domain\Training\AssigneeKind`, `ObligationState`, `ReminderStage`, `TrainingNotice`, `CreditUnit` | À qui un cours est assigné, où en est un apprenant, les rappels, les avis, les unités de crédits |
| `Domain\ValueObject\AssignmentId`, `TranscriptEntryId` | Les identifiants d'une assignation et d'une entrée du relevé |
| `Domain\ValueObject\Credits` | Des crédits en centièmes, avec leur unité |
| `Domain\Xapi\XapiVerb` | Les verbes d'ADL avec lesquels une activité xAPI rapporte, et `voided` |
| `Domain\ValueObject\CourseId`, `ModuleId`, `BlockId`, `ActivityId`, `EnrollmentId`, `RetryRequestId`, `LaunchSessionId` | Des identifiants, UUID version 7 quand le bundle les tire |
| `Domain\ValueObject\LearnerId` | L'identifiant stable d'un apprenant, jamais une adresse courriel |
| `Domain\ValueObject\Release` | Tout de suite, à un moment, ou un nombre de jours après l'inscription |
| `Domain\ValueObject\Score` | Un score normalisé, de -1 à 1 |
| `Domain\ValueObject\Outcome` | Ce qu'un apprenant a fait : terminé, réussi ou échoué, score |
| `Domain\ValueObject\Availability` | Où en est une activité pour un apprenant, et ce qui reste à faire |
| `Domain\Progress\CompletionRequirement` | Les valeurs du `moveOn` de cmi5 |
| `Domain\Progress\AvailabilityStatus` | `open`, `satisfied`, `scheduled`, `locked`, `retry_needed`, `retry_requested` |
| `Domain\Progress\RetryDecision` | `pending`, `granted`, `refused`, `waived` |
| `Domain\Course\CourseOrigin` | `native`, `scorm12`, `scorm2004`, `cmi5`, `xapi` ; `of()` le format d'un paquet |
| `Domain\Cmi5\Cmi5Verb`, `Cmi5Iri`, `SessionState` | Les verbes, les IRI et les états de session de cmi5 ; les modes de lancement sont le `LaunchMethod` de phil/elearning-packages |
| `Domain\Contract\CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`, `LaunchSessionRepository`, `ScormAttemptRepository`, `AssignmentRepository`, `TranscriptRepository` | Les entrepôts |
| `Domain\Event\ScormSessionEnded` | Une session d'une activité SCORM a pris fin, et si sa tentative aussi |
| `Domain\Exception\AttemptRefused` | Une validation refusée : son jeton, son apprenant ou sa session n'est pas celui de la tentative |
| `Domain\Event\*` | `LearnerEnrolled`, `ActivityProgressed`, `ActivitySatisfied`, `CourseCompleted`, `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived`, `BlockSatisfied`, `CourseImported`, `StatementRecorded`, `TrainingAssigned`, `ObligationOverdue`, `ObligationRenewed`, `EnrollmentRenewed`, `CreditsGranted` |
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
| `Application\ActivityType\PageActivity`, `LinkActivity`, `ScormActivity`, `Cmi5Activity`, `XapiActivity` | Les adaptateurs des types d'activité |
| `Application\Launch`, `LaunchWindow`, `LaunchUrl` | L'endroit où l'apprenant va pour suivre une activité, et les paramètres ajoutés à son URL |
| `Application\ContentUrls` | Les adresses des fichiers sur l'origine du contenu |
| `Application\CourseOutline`, `ModuleOutline`, `ActivityStatus` | Un cours lu comme un plan, et où en est une activité |
| `Application\Contract\ActivityType`, `CurrentLearner`, `ContentStorage`, `LearningRecordStore`, `Cmi5Endpoints`, `XapiPackageReader`, `AssigneeMembers`, `TrainingNotifier` | Les ports ; les paquets sont ouverts par le `PackageOpener` de phil/elearning-packages |
| `Application\Import\CourseImporter` | Importe un paquet ou une structure de cours, lus par phil/elearning-packages |
| `Application\Import\ScormPlanner`, `Cmi5Planner`, `XapiPlanner`, `ImportPlan`, `PlannedModule`, `PlannedBlock`, `PlannedActivity` | Transforment un manifeste en cours à créer |
| `Application\Import\XapiPackage`, `XapiLaunchable` | Ce qu'un lecteur de paquets xAPI a trouvé |
| `Application\Import\ImportReport` | Ce qu'un import a créé et laissé de côté |
| `Application\Import\ResultImporter`, `ScormStatus` | Les résultats importés |
| `Application\Scorm\ScormRuntime` | Ouvre les sessions des tentatives SCORM, conserve leurs validations, y met fin — à leur échéance aussi — et enregistre leur résultat ; ouvre une tentative terminée en révision |
| `Application\Scorm\ScormTokens` | Les jetons de tentative |
| `Application\Scorm\ScormLaunch`, `CommitAnswer` | Ce qu'un lancement ou une révision remet au navigateur, et ce qu'une validation répond |
| `Application\Scorm\TimeLimit` | Le temps alloué à une tentative : celui du manifeste, le `time_limit` propre à l'activité, ou le plus court, et son échéance |
| `Application\Cmi5\Cmi5Launcher` | Lance une unité |
| `Application\Cmi5\Cmi5Tokens` | La réponse de l'URL de récupération, et les jetons |
| `Application\Cmi5\Cmi5Statements` | Reçoit et émet des énoncés |
| `Application\Cmi5\StatementRules` | Les règles de cmi5 |
| `Application\Cmi5\PlatformStatements`, `LaunchData`, `Cmi5Context`, `Cmi5Settings` | Les énoncés de la plateforme, les données de lancement, le contexte d'un lancement, les paramètres |
| `Application\Cmi5\IssueWaivedStatement` | Émet l'énoncé `waived` d'une unité dispensée, puis les énoncés `satisfied` qu'elle entraîne |
| `Application\Xapi\XapiLauncher` | Lance une activité xAPI avec les paramètres que lisent les paquets xAPI |
| `Application\Xapi\XapiStatements`, `XapiRules`, `XapiOutcomes`, `XapiContext` | Reçoivent les énoncés d'une activité xAPI, les vérifient, lisent leur résultat |
| `Application\Xapi\StatementBatch` | Les limites du corps d'une requête à la ressource des énoncés |
| `Application\Training\Assignments`, `TrainingStatus` | Assignent les cours, joignent les membres des groupes et des unités, terminent les obligations, et disent où en est un apprenant |
| `Application\Training\TrainingRun`, `TrainingReport`, `TrainingSettings` | Une exécution de rappels, de renouvellements et de nouveaux membres, et ses réglages |
| `Application\Training\Recertification` | Quand un achèvement est renouvelé, par phil/recurrence |
| `Application\Training\Transcripts`, `Transcript` | Les crédits des cours et les relevés, en tableau et en CSV |
| `Application\Training\TrainingCompletions` | Écoute `CourseCompleted` : les obligations, puis le relevé |
| `Application\Message\RunTraining`, `Application\Handler\RunTrainingHandler` | Le message du calendrier et son handler |
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
| `Infrastructure\Doctrine\DbalAssignmentRepository`, `DbalTranscriptRepository` | Les assignations, obligations, relevés et crédits par DBAL |
| `Infrastructure\Http\TrainingController` | La formation et le relevé de l'apprenant, les pages des gestionnaires |
| `Infrastructure\Notifications\NotificationsTrainingNotifier` | Les avis de la formation par phil/notifications-bundle, et les types qu'il déclare |
| `Infrastructure\Scheduler\TrainingSchedule` | Le calendrier `phil_courses_training` |
| `Infrastructure\Privacy\TrainingPrivacy` | Les obligations et le relevé dans les demandes d'accès et d'effacement de phil/privacy-bundle |
| `Infrastructure\Privacy\ScormPrivacy` | Les tentatives SCORM dans les demandes d'accès et d'effacement de phil/privacy-bundle |
| `Infrastructure\Privacy\Cmi5Privacy` | Les sessions de lancement cmi5 et xAPI, énoncés, documents d'état et notes des énoncés `satisfied` dans les demandes d'accès et d'effacement de phil/privacy-bundle, et ce que conserve un entrepôt extérieur au bundle |
| `Infrastructure\Http\SecondChancesController` | La page d'administration des secondes chances |
| `Infrastructure\Routing\RoutedCmi5Endpoints` | Les URL du point de terminaison et de l'URL de récupération |
| `Infrastructure\Console\ImportCommand`, `ImportResultsCommand`, `RetriesCommand`, `AssignCommand`, `CreditsCommand`, `TrainingCommand` | Les commandes |
| `Infrastructure\Audit\AuditSecondChances` | La piste des secondes chances |
| `Infrastructure\Security\SecurityCurrentLearner` | L'apprenant tiré de Symfony Security |
| `Infrastructure\Twig\CoursesExtension`, `KitComponents` | `phil_courses_kit()` et les noms des recettes |
| `Infrastructure\DependencyInjection\CoursesServices`, `KitSubdirectoryPass` | Les services |

## Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `phil:courses:import <path> [--title=]` | Importe un paquet SCORM, cmi5 ou xAPI, ou une structure de cours cmi5 |
| `phil:courses:import-results <course> <file>` | Importe une exportation CSV de résultats |
| `phil:courses:retries [--grant=] [--refuse=] [--waive=] [--by=] [--note=]` | Énumère les secondes chances, ou en accorde, en refuse ou en dispense une avec la note de l'enseignant |
| `phil:courses:assign <course> <kind> <assignee> <due> [--renew-months=] [--by=]` | Assigne un cours à un apprenant, un groupe ou une unité, dû à la fin d'un jour dans le fuseau horaire du site |
| `phil:courses:credits <course> <amount> [--unit=]` | Fixe les crédits qu'un cours accorde, en `ceu` ou `hours` ; `0` pour aucun |
| `phil:courses:training` | Exécute maintenant les rappels, les renouvellements et les nouveaux membres des groupes et des unités |
