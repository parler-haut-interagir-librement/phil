<!-- phil-dev source-sha256=cc5976c3b8bbaea780bcb3350ebeeab502e0613307458a78cb9cf6d9f4f575e3 -->
# Parcours d'apprentissage

Un parcours d'apprentissage est une suite ordonnée de cours : l'accueil en quatre cours, une certification en six.
Chaque cours du parcours est une étape, obligatoire ou non, qui attend des cours placés avant elle. Un apprenant
s'inscrit une fois au parcours, et le bundle l'inscrit à chaque cours à mesure que ses prérequis sont terminés. Le
parcours est terminé une fois chaque étape obligatoire terminée.

## Définir un parcours

`LearningPaths` écrit et lit les parcours :

```php
use Phil\CoursesBundle\Application\Path\LearningPaths;

$path = $paths->create('Welding', 'From the first bead to the final project.');

$paths->addStep($path->id, $basics->id);
$paths->addStep($path->id, $tools->id, required: false);
$paths->addStep($path->id, $safety->id, prerequisites: [$basics->id]);
$paths->addStep($path->id, $project->id, prerequisites: [$basics->id, $safety->id]);

// A step goes before another one rather than at the end.
$paths->addStep($path->id, $welcome->id, before: $basics->id);

$paths->removeStep($path->id, $tools->id);
$paths->rename($path->id, 'Arc welding', 'Arcs, beads and joints.');
$paths->remove($path->id);
```

| Règle | Refus |
|---|---|
| Un cours est étape d'un parcours une seule fois | `InvalidCourse` : `The course "…" is already a step of the path.` |
| Un prérequis est une étape placée avant la nouvelle, de sorte que les prérequis ne contiennent aucun cycle | `InvalidCourse` : `The prerequisite "…" is not a step placed before this one.` |
| Une étape attend 50 cours au plus, chacun une fois, jamais elle-même | `InvalidCourse` |
| Un parcours contient 100 étapes au plus | `InvalidCourse` : `A path holds 100 steps at most.` |
| Le parcours et le cours existent | `UnknownRecord` |

Les étapes gardent leur ordre ; `steps()` les donne comme `PathStep` — le cours, sa place à partir de 0, s'il est
obligatoire, et ses prérequis. Une étape retirée est ôtée des prérequis des autres. Retirer un parcours retire ses étapes
et ses inscriptions ; les apprenants restent inscrits à ses cours. Retirer un cours retire ses étapes dans chaque
parcours ; un prérequis qui n'est plus une étape ne retient plus rien.

## S'inscrire et avancer

```php
$enrollment = $paths->enroll($path->id, $learner);
```

`enroll()` conserve un `PathEnrollment` — une fois par apprenant et par parcours ; un second appel rend le premier —,
envoie `LearnerEnrolledInPath`, puis inscrit l'apprenant à chaque cours dont il a terminé les prérequis. Chaque
achèvement de cours qui suit fait avancer les inscriptions ouvertes de l'apprenant dans les parcours qui le contiennent :
`PathCompletions` écoute `CourseCompleted`, inscrit l'apprenant aux cours que l'achèvement ouvre, et termine le parcours
une fois chaque étape obligatoire terminée. Un cours terminé avant l'inscription compte. Les prérequis commandent
l'inscription que fait le parcours ; un site qui laisse les apprenants s'inscrire eux-mêmes à un cours laisse cette
porte ouverte.

`progress()` donne un `PathProgress` : l'inscription, chaque étape comme `StepStatus` avec son état — `locked`, `open`
ou `completed` — et l'achèvement qui l'a terminée, et la part des étapes obligatoires terminées, de 0 à 100.

```php
foreach ($paths->progress($enrollment)->steps as $status) {
    echo $status->step->course->value, ' ', $status->state->value, \PHP_EOL;
}
```

Une étape est terminée par son cours ou par un cours équivalent de son [groupe](equivalent-courses.md) : l'achèvement
nomme alors le cours que l'apprenant a suivi. Un parcours sans étapes n'est jamais terminé.

## Certificats et insignes

`PathCompleted` est envoyé une seule fois, quand le parcours est terminé, avec l'inscription, le parcours et l'apprenant.
Une application émet un insigne à partir de cet événement avec un écouteur à elle, comme elle le fait à partir de
`CourseCompleted` pour un cours.

Avec phil/certificates-bundle, un cours de certificat nomme un parcours comme source d'achèvement par `path:` suivi de
l'identifiant du parcours :

```php
$courses->define('welding', 'Welding', 'The path of the welders.', 'Complete every course of the path.', completionSource: 'path:'.$path->id->value);
```

Le bundle répond au port par l'identifiant de l'inscription de l'apprenant au parcours terminé, que le certificat garde
comme preuve. `LearningPaths::completion()` répond de même pour une autre partie de l'application.

## Événements

| Événement | Porte |
|---|---|
| `LearnerEnrolledInPath` | l'inscription au parcours, le parcours, l'apprenant |
| `PathCompleted` | l'inscription au parcours, le parcours, l'apprenant |

## Vie privée

Avec phil/privacy-bundle, `PathsPrivacy` donne les inscriptions d'un apprenant aux parcours — le parcours, le début, la
fin — sous le module `courses_paths`, et les efface lors d'un effacement. Les inscriptions aux cours eux-mêmes relèvent
des autres modules du bundle.

## Pages

Le bundle ne dessine encore aucune page pour les parcours : l'application les liste, y inscrit un apprenant et montre sa
progression avec `LearningPaths`, chaque cours menant à son plan à la route `phil_courses_outline`.
