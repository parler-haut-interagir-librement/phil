<!-- phil-dev source-sha256=cd95d6e0bd46e71bd014a889d387e0d27c621ae9b52e0fde30f7e85953642d18 -->
# Le modèle de cours

## Cours, modules et activités

Un **cours** a un titre, une description, une origine — écrit dans l'application, ou importé d'un paquet SCORM 1.2,
SCORM 2004, cmi5 ou xAPI, dont il garde l'identifiant comme source — et une indication de l'obligation de suivre ses
activités dans l'ordre. Il contient des **modules** dans l'ordre, jusqu'à 500, et chaque module contient des
**activités** dans l'ordre, jusqu'à 500.

`Courses` les écrit et les lit :

```php
$course = $courses->create('Workshop safety', 'Tools and habits.', ordered: true);
$basics = $courses->addModule($course->id, 'Basics');
$week2 = $courses->addModule($course->id, 'Week 2', Release::daysAfterEnrollment(7));

$courses->addActivity($basics->id, 'Read me first', 'page', ['body' => 'Wear goggles.']);
$courses->addActivity($basics->id, 'The manual', 'link', ['url' => 'https://example.org/manual']);
$courses->addActivity($week2->id, 'Final check', 'page', [], CompletionRequirement::Passed, required: true, maxAttempts: 2);

$outline = $courses->outline($course->id); // the course, its modules and their activities
```

Une activité a un **type**, qui nomme l'adaptateur qui vérifie ses paramètres et la lance :

| Type | Paramètres | S'ouvre | Marquée terminée par |
|---|---|---|---|
| `page` | `body`, texte brut | Sur la page de l'activité | L'apprenant |
| `link` | `url`, une adresse absolue | Dans sa propre fenêtre | L'apprenant |
| `scorm` | `path` et `query` de son fichier de lancement, `scorm_version`, `time_limit` — sa propre limite de temps, en secondes —, et ce que l'import a lu du manifeste | Dans un cadre, depuis l'origine du contenu, sous l'environnement d'exécution SCORM | Son contenu, par l'environnement d'exécution ; l'apprenant quand il est désactivé |
| `cmi5` | `au_id`, `url` ou `path` et `query`, `launch_method`, `mastery_score`, `launch_parameters`, `entitlement_key` | Dans un cadre, ou dans sa propre fenêtre quand elle le demande | Ses propres énoncés seulement |
| `xapi` | `activity_id`, `url` ou `path` et `query`, `launch_method`, `mastery_score` | Dans un cadre, ou dans sa propre fenêtre quand elle le demande | Ses propres énoncés `completed`, `passed` et `failed` seulement ; voir [activités xAPI](xapi-activities.md) |

Une application ajoute ses propres types : voir [Points d'extension](extension-points.md).

## Blocs

Un cours importé de cmi5 conserve aussi les **blocs** de sa structure de cours sous forme d'arbre. Un `Block` a un
titre, l'identifiant que lui donne la structure de cours comme source, le bloc dans lequel il est imbriqué — aucun au
premier niveau — et sa place parmi les enfants de ce parent, blocs et unités comptés ensemble. Chaque activité du cours
nomme le bloc le plus interne qui la contient, dans `Activity::$block` ; une unité du premier niveau n'en nomme aucun.
Les modules restent le plan que voit l'apprenant ; les blocs sont ce que suivent les énoncés `satisfied` de cmi5 — voir
[cmi5](cmi5.md).

```php
$charts = $courses->addBlock($course->id, 'Charts', 'https://example.org/blocks/charts', 0);
$tides = $courses->addBlock($course->id, 'Tides', 'https://example.org/blocks/tides', 1, $charts->id);
$courses->addActivity($module->id, 'Tide tables', 'cmi5', $settings, block: $tides->id);

$courses->blocks($course->id); // each parent before the blocks it holds, siblings in their order
```

## Ce qui satisfait une activité

Chaque activité porte une **exigence**, les valeurs du `moveOn` de cmi5, quel que soit son type :

| Exigence | Satisfaite quand |
|---|---|
| `Completed` | l'apprenant l'a terminée |
| `Passed` | l'apprenant l'a réussie |
| `CompletedAndPassed` | les deux |
| `CompletedOrPassed` | l'un ou l'autre |
| `NotApplicable` | l'apprenant l'a ouverte |

Une activité est **obligatoire** ou non ; le cours est terminé quand chaque activité obligatoire est satisfaite. Une
activité dispensée compte comme satisfaite.

## Inscriptions et progression

`Enrollments::enroll()` inscrit un apprenant une seule fois à un cours ; s'inscrire de nouveau redonne la même
inscription. Chaque inscription a un enregistrement, un UUID qui relie ses énoncés cmi5. L'apprenant est nommé par
l'identifiant stable de son compte, jamais par une adresse courriel.

La progression d'une inscription dans une activité conserve le moment où elle a commencé, si elle est terminée, réussie
ou échouée, si elle est dispensée, le meilleur score, les essais échoués, les secondes chances accordées, et le moment
où elle a été satisfaite. Ce que l'apprenant a atteint est conservé : un échec après une réussite laisse l'activité
réussie, un score plus bas laisse le meilleur.

```php
$enrollment = $enrollments->enroll($course->id, LearnerId::of('m-1042'));
$enrollments->record($enrollment, $activity, Outcome::passed(Score::scaled(0.85)));
$enrollments->percent($enrollment); // the share of required activities satisfied, from 0 to 100
```

Sur les pages, `Learning::launch()` et `Learning::complete()` font de même pour l'apprenant connecté, une fois l'activité
ouverte pour lui.

## Ouvertures

Un module ou une activité s'ouvre tout de suite, à une date, ou un nombre de jours après l'inscription :

```php
Release::immediately();
Release::on(new DateTimeImmutable('2026-12-01 09:00', new DateTimeZone('America/Toronto')));
Release::daysAfterEnrollment(3);
```

Les jours se comptent à partir de minuit du jour de l'inscription dans le fuseau horaire du site,
`phil_courses.timezone` : un apprenant qui s'inscrit à 22 h 30 le 12 voit le « jour 3 » s'ouvrir à minuit le 15. Une
activité s'ouvre une fois que son module et elle-même sont ouverts.

## Où en est une activité

`Availabilities::outline()` dit, pour chaque activité du cours de l'apprenant, où elle en est :

| État | Signification | Ce que l'apprenant peut faire |
|---|---|---|
| `open` | Ouverte, non satisfaite | La lancer |
| `satisfied` | Faite comme l'exige son exigence | La revoir |
| `scheduled` | Ouverte plus tard ; le moment est donné | Attendre |
| `locked` | Dans un cours à ordre imposé, une activité obligatoire antérieure n'est pas satisfaite ; elle est nommée | Satisfaire d'abord celle-là |
| `retry_needed` | Tous les essais ont été échoués | Demander une seconde chance |
| `retry_requested` | Une seconde chance est demandée | Attendre l'enseignant |

Aucun état n'est une impasse : une activité planifiée s'ouvre, une activité verrouillée s'ouvre avec l'activité qui la
précède, et un apprenant sans essai restant demande une seconde chance, ou un enseignant dispense l'activité.

## Secondes chances

Une activité peut permettre un nombre d'essais ; un échec en compte un. Un apprenant qui les a tous échoués demande une
seconde chance en donnant une raison. Un enseignant la tranche une seule fois, avec une note de 2 000 caractères au
plus : il l'accorde, ce qui donne un essai de plus, la refuse, ou dispense l'activité à la place, que le cours compte
alors satisfaite — une unité cmi5 a son énoncé `waived` :

```php
$request = $secondChances->request($enrollment, $activity->id, 'I was ill on the day.');
$secondChances->grant($request->id, 'teacher-7', 'One more try, on Friday.');
$secondChances->refuse($request->id, 'teacher-7', 'The exam is closed.');
$secondChances->waive($request->id, 'teacher-7', 'Done at the workshop.');
$enrollments->waive($enrollment, $activity); // a waiver without a request
```

`SecondChances::review()` donne les demandes en attente, les plus anciennes d'abord, chacune comme un `RetryReview` avec
son inscription, son cours, son activité et la progression de l'apprenant : `failures()` et `tries()`, les essais de
l'activité et les secondes chances accordées, null pour aucune limite.

`phil:courses:retries` énumère les demandes en attente, et en tranche une avec `--grant`, `--refuse` ou `--waive`,
`--by` et une `--note` facultative. Avec phil/audit-bundle enregistré, chaque demande, chaque octroi, chaque refus et
chaque dispense est écrit dans sa piste, avec des identifiants seulement : jamais la raison ni la note, qui peuvent
contenir des mots personnels.

### La page d'administration

Les routes de `config/admin_routes.php`, que l'application importe sous le préfixe de son choix et derrière son pare-feu,
donnent aux enseignants une page des demandes en attente :

```yaml
# config/routes/phil_courses.yaml
phil_courses_admin:
    resource: '@PhilCoursesBundle/config/admin_routes.php'
    prefix: /admin/courses
```

| Route | Page |
|---|---|
| `phil_courses_admin_retries` | `GET /second-chances` : chaque demande en attente avec le cours, l'activité, l'apprenant, le moment de la demande, les essais échoués et la raison |
| `phil_courses_admin_retry_decide` | `POST /second-chances/{retry}` : accorde, refuse ou dispense, avec la note |

La page demande le rôle `phil_courses.admin.role` — `ROLE_COURSES_TEACHER` par défaut — par le vérificateur
d'autorisation de Symfony Security, et refuse quiconque n'en a pas. La décision est prise au nom de l'enseignant
connecté, tel que le service `CurrentLearner` le nomme. Le formulaire porte le jeton CSRF `phil_courses` ; un jeton
manquant, une décision autre que `grant`, `refuse` ou `waive`, une demande déjà tranchée ou une note trop longue répond
`400`, une demande inconnue `404`. La page dessine les recettes `Badge`, `Progress`, `Textarea` et `Button` du kit, en
anglais et en français, et répond `Cache-Control: no-store`.

## Événements

Le bundle envoie ces événements par le répartiteur PSR-14 ; chacun ne porte que des identifiants.

| Événement | Quand |
|---|---|
| `LearnerEnrolled` | Un apprenant s'est inscrit à un cours |
| `ActivityProgressed` | La progression d'un apprenant dans une activité a changé |
| `ActivitySatisfied` | Une activité est devenue satisfaite, par son exigence ou par une dispense |
| `CourseCompleted` | Un apprenant a satisfait chaque activité obligatoire d'un cours ; `Completions` répond ensuite la même chose |
| `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived` | Une seconde chance a été demandée, accordée ou refusée, ou son activité dispensée |
| `BlockSatisfied` | La plateforme a émis l'énoncé `satisfied` d'un bloc d'un cours cmi5 pour une inscription |
| `CourseImported` | Un cours a été importé d'un paquet |
| `StatementRecorded` | Un énoncé cmi5 ou xAPI a été accepté ou émis, et remis à l'entrepôt de traces d'apprentissage |
| `TrainingAssigned`, `ObligationOverdue`, `ObligationRenewed`, `EnrollmentRenewed`, `CreditsGranted` | La formation obligatoire : voir [formation obligatoire](mandatory-training.md#événements) |

## Les pages

| Route | Page |
|---|---|
| `phil_courses_outline` | Le plan : la progression de l'apprenant, chaque module et ses activités avec l'état de chacune |
| `phil_courses_enroll` | L'inscription, quand `self_enrollment` est activé |
| `phil_courses_launch` | La page de l'activité : le texte d'une page, le contenu encadré depuis l'origine du contenu, ou le lien vers sa fenêtre |
| `phil_courses_complete` | Marque une activité terminée, quand son type le permet à l'apprenant |
| `phil_courses_retry` | Demande une seconde chance |
| `phil_courses_training` | Les cours assignés à l'apprenant et où il en est ; voir [formation obligatoire](mandatory-training.md) |
| `phil_courses_transcript` | Le relevé de l'apprenant, en page, JSON ou CSV ; voir [crédits et relevés](credits-and-transcripts.md) |

Chaque formulaire porte le jeton CSRF sans état `phil_courses`, et une requête que le navigateur dit venir d'un autre
site est refusée. Les pages répondent avec `Cache-Control: no-store`.
