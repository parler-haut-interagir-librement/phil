<!-- phil-dev source-sha256=b3ed97bf1e3524e2d2706037a0199a6d446a10413e263d8a766e4c3dd5d714c8 -->
# Points d'extension

Le bundle est en versions `0.x` : les ports, les événements et les services ci-dessous peuvent changer dans une version
mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de la version `1.0`.

## `ActivityType`

Un type d'activité, dans `Phil\CoursesBundle\Application\Contract` : le nom que garde une activité, les paramètres qu'il
vérifie, si l'apprenant la marque terminée, et où l'apprenant va pour la suivre. Un service qui l'implémente est recueilli
avec l'étiquette `phil_courses.activity_type`, ajoutée par la configuration automatique. Le bundle a `page`, `link`,
`scorm` et `cmi5`.

```php
final readonly class WorkshopActivity implements ActivityType
{
    public function __construct(private UrlGeneratorInterface $urls) {}

    public function name(): string
    {
        return 'workshop';
    }

    public function settings(array $settings): array
    {
        return ['room' => (string) ($settings['room'] ?? '')];
    }

    public function completesManually(): bool
    {
        return true;
    }

    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        return Launch::newWindow($this->urls->generate('app_workshop', ['room' => $activity->stringSetting('room')], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
```

Un type dont le contenu rapporte lui-même son résultat répond `false` à `completesManually()`, et appelle
`Enrollments::record()` quand le résultat arrive.

## `CurrentLearner`

Qui suit les cours dans la requête, ou null. `SecurityCurrentLearner` lit l'identifiant d'utilisateur de Symfony Security
et refuse un identifiant qui contient un `@` ; une application dont les utilisateurs se connectent avec leur adresse
courriel nomme son propre service dans `phil_courses.current_learner`, qui retourne un `LearnerId` d'un identifiant
stable.

## `ContentStorage`

L'endroit où sont conservés les fichiers des paquets importés : `write()`, `exists()`, `read()` et `removeDirectory()`,
sur des chemins relatifs que l'import a vérifiés. `FilesystemContentStorage` écrit dans `phil_courses.storage.directory`
; une application nomme le sien dans `phil_courses.storage.service`, un stockage d'objets par exemple. L'origine du
contenu sert alors les fichiers à partir de là.

## `LearningRecordStore`

L'endroit où sont conservés les énoncés cmi5 et les documents d'état : `store()`, `state()`, `saveState()` et
`deleteState()`. `DbalLearningRecordStore` les conserve par DBAL. `HttpLearningRecordStore`, activé par
`phil_courses.cmi5.remote_lrs`, envoie les énoncés à un entrepôt xAPI distant et garde les documents d'état en local, dans
`DbalLearningRecordStore` : le port ne nomme aucun agent, que la ressource State de xAPI exige. Un adaptateur de l'application, nommé dans `phil_courses.cmi5.lrs`, les conserve ailleurs ; il
reçoit des énoncés que le bundle a déjà vérifiés selon les règles de cmi5, avec leur `id` fixé. Un adaptateur qui ne peut
pas conserver un énoncé lance `RecordStoreUnavailable` : le point de terminaison xAPI répond `503`, et un énoncé
`satisfied` qu'il a refusé est émis de nouveau plus tard.

## `PackageOpener` et `PackageArchive`

L'ouverture d'un paquet en lecture : les contrats de phil/elearning-packages. `ZipPackageOpener` lit les archives ZIP par
l'extension zip de PHP dans les limites de l'import ; une application qui reçoit des paquets sous une autre forme fait de
`Phil\ElearningPackages\Contract\PackageOpener` un alias vers son propre service, dont l'archive garde les mêmes
vérifications.

`Phil\ElearningPackages\PackageReader` est un service qui a les limites et la langue de `phil_courses.import` : un
formulaire de téléversement y vérifie un paquet avant l'import.

## `Completions` et `CourseCompletions`

`Completions::completion(CourseId $course, LearnerId $learner)` répond si un apprenant a terminé un cours : une
`Completion` — l'inscription, le cours, l'apprenant et le moment, en UTC — ou null. Elle suit la règle qui déclenche
`CourseCompleted`, et un achèvement reste acquis une fois atteint. Une autre partie de l'application qui garde son propre
modèle — certificats, insignes, rapport — la consulte plutôt que de lire les entrepôts.

Avec phil/certificates-bundle 0.2 ou une version plus récente, qui déclare le port `CourseCompletions`, le bundle enregistre
`CertificatesCourseCompletions` et fait du port un alias vers lui. phil/certificates-bundle lui demande alors
l'achèvement d'un cours, par l'identifiant du cours dans ce bundle et celui de l'apprenant, et reçoit l'identifiant de
l'inscription qui l'a terminé, ou null. Un identifiant que ce bundle ne peut pas contenir ne désigne aucun achèvement.

## `Cmi5Endpoints`

Les URL absolues du point de terminaison xAPI et de l'URL de récupération d'une session. `RoutedCmi5Endpoints` les génère
à partir des routes de `config/cmi5_routes.php`.

## `CourseRepository` et `EnrollmentRepository`

Les entrepôts des cours et des inscriptions, des alias de leurs ports vers les entrepôts DBAL. `CourseRepository` conserve
les blocs d'un cours cmi5 avec `saveBlock()` et `blocks()` ; `EnrollmentRepository` note les énoncés `satisfied` qu'une
inscription a avec `satisfactions()`, `claimSatisfaction()` — qui répond false quand la satisfaction était déjà notée,
de sorte que deux requêtes simultanées émettent un seul énoncé — et `releaseSatisfaction()`. Une application qui
remplace un entrepôt les implémente.

## `ScormAttemptRepository`

L'endroit où sont conservées les tentatives SCORM et leurs données de suivi : `save()`, `find()`, `latest()` — la dernière
tentative de l'apprenant à une activité —, `ofEnrollments()` et `removeOfEnrollments()`, par lesquels le module de vie
privée lit et efface. `DbalScormAttemptRepository` les conserve par DBAL ; une application qui les conserve ailleurs fait
de l'alias du port son propre service.

## Événements

Les événements du [modèle de cours](course-model.md#événements) sont envoyés par le répartiteur PSR-14, et ne portent que
des identifiants : un écouteur lit le reste dans les entrepôts. Un écouteur envoie le rappel d'une ouverture, décerne un
certificat à `CourseCompleted`, prévient un enseignant d'un `RetryRequested`, ou suit le parcours d'un apprenant dans les
blocs d'un cours cmi5 à `BlockSatisfied`. `ScormSessionEnded` est envoyé à la fin de chaque session d'une activité SCORM,
avec l'indication que sa tentative a pris fin ou non.

## Services

Chaque point d'entrée est un service public nommé par sa classe : `Courses`, `Enrollments`, `Availabilities`,
`Learning`, `Completions`, `SecondChances`, `CourseImporter`, `ResultImporter`, `Cmi5Statements`, `Cmi5Tokens`,
`Cmi5Launcher`, `ActivityTypes`, `ScormRuntime`, `ScormTokens`, et les contrôleurs des pages, dont
`SecondChancesController`, `ScormController` et `ScormFrameController`.
Les entrepôts sont des alias de leurs ports : `CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`,
`LaunchSessionRepository`, `ScormAttemptRepository`.
