<!-- phil-dev source-sha256=2e642bbe12a3f0f48d7b71ffda1586c06761fa69927db873253fccfae6fb9499 -->
# Formation obligatoire

Un cours peut être assigné : à un apprenant, aux membres d'un groupe ou aux membres d'une unité d'une organisation,
pour une échéance, et renouvelé après un nombre de mois pour une recertification. Chaque apprenant joint est inscrit et
reçoit une obligation, dont il est relancé avant l'échéance, le jour même et après ; elle est montrée en retard à
l'apprenant et aux gestionnaires. Cette partie est activée par défaut ; `phil_courses.training.enabled: false` la
laisse de côté.

## Assigner un cours

```php
$assignments->assign(
    $course->id,
    AssigneeKind::Learner,          // Learner, Group or Unit
    'member-42',                    // the identifier of the learner, the group or the unit
    new DateTimeImmutable('2026-12-01 00:00', new DateTimeZone('America/Toronto')),
    12,                             // renewed 12 months after each completion; null for a course taken once
    LearnerId::of('manager-7'),     // who assigns it, told once it is overdue
);
```

```sh
php bin/console phil:courses:assign <course> learner member-42 2026-11-30 --renew-months=12 --by=manager-7
```

L'échéance est une date limite : la commande lit sa date comme le dernier jour, dans le fuseau horaire du site, et le
cours est dû avant le minuit qui le termine. L'assignation est refusée quand le cours est inconnu, quand l'échéance
précède l'assignation, quand le renouvellement est hors de 1 à 120 mois, ou quand l'assigné n'est pas un identifiant.

Chaque membre joint est inscrit au cours et reçoit une obligation pour le premier cycle, due à l'échéance de
l'assignation, et il est averti que le cours est assigné. Un membre qui a déjà terminé le cours commence terminé, et son
renouvellement se compte depuis cet achèvement.

### Groupes et unités

Le bundle ne tient ni groupes ni organisations. Les membres d'un groupe ou d'une unité viennent d'un service
`AssigneeMembers` de l'application — configuré automatiquement, étiquette `phil_courses.assignee_members` — ; le premier
qui prend en charge le type répond.

```php
final readonly class TeamMembers implements AssigneeMembers
{
    public function __construct(private TeamRepository $teams) {}

    public function supports(AssigneeKind $kind): bool
    {
        return AssigneeKind::Group === $kind;
    }

    public function members(AssigneeKind $kind, string $assignee): iterable
    {
        foreach ($this->teams->members($assignee) as $member) {
            yield $member->id->toRfc4122();
        }
    }
}
```

Sans adaptateur pour son type, l'assignation est refusée. Une assignation joint `phil_courses.training.max_members`
membres au plus, 10 000 par défaut ; un groupe plus grand est refusé. Chaque exécution donne une obligation aux membres
arrivés depuis ; un membre parti garde la sienne.

## Où en est un apprenant

| État | Quand |
|---|---|
| `pending` | Assignée, avant la fenêtre de rappel. |
| `due_soon` | À moins de `reminder_days` jours de l'échéance, 7 par défaut. |
| `overdue` | L'échéance est passée, le cours n'est pas terminé dans ce cycle. |
| `completed` | Terminée dans ce cycle. |

L'apprenant voit ses cours assignés sur la page `phil_courses_training` (`/courses/training` avec la recette), le plus
proche de l'échéance d'abord, chacun avec sa date d'échéance et son état — le retard dans la variante destructive du
`Badge` de la recette —, et un lien vers son [relevé](credits-and-transcripts.md).

Un gestionnaire — qui détient `phil_courses.training.manager_role`, `ROLE_COURSES_MANAGER` par défaut — voit sur la page
`phil_courses_admin_training` (`/admin/courses/training`) les obligations de chaque apprenant en retard ou bientôt dues,
la plus proche de l'échéance d'abord, chacune avec un lien vers le relevé de cet apprenant. Sans vérificateur
d'autorisation, la page est refusée à tout le monde.

## Les rappels

Chaque exécution relance tout apprenant dont l'obligation n'est pas terminée, une fois par cycle pour chaque étape :

| Étape | Quand | Notification |
|---|---|---|
| Avant l'échéance | à moins de `reminder_days` jours de celle-ci | `courses.training.due_soon` |
| Le jour de l'échéance | dans son dernier jour | `courses.training.due` |
| En retard | une fois passée | `courses.training.overdue`, et `courses.training.overdue_manager` à la personne qui a assigné |

Une exécution qui atteint une obligation pour la première fois en retard n'envoie que l'étape de ce moment.
`ObligationOverdue` est envoyée avec le rappel de retard.

Avec phil/notifications-bundle enregistré, le bundle déclare les types `courses.training.assigned`, `due_soon`, `due`,
`overdue`, `overdue_manager` et `renewed` — dans le centre du site et par courriel, ceux de retard d'importance élevée ;
une application qui déclare un type de même clé le modifie — et les envoie par son service `Notifications`, au sujet du
cours, avec des identifiants seulement : l'assignation, l'apprenant, le cycle et l'échéance, `YYYY-MM-DD` en UTC. Leurs
gabarits anglais et français sont dans le domaine `phil_notifications` du bundle. Une application qui avertit ses gens
autrement nomme son propre `TrainingNotifier` dans `phil_courses.training.notifier`.

## L'exécution

`TrainingRun` met à jour les groupes et les unités, envoie les rappels et renouvelle les recertifications. Les
obligations sont lues par lots de 500, de sorte qu'une exécution garde un nombre borné en mémoire, et aucun service ne
garde d'état entre les exécutions.

Avec symfony/scheduler et symfony/messenger, le calendrier `phil_courses_training` envoie `RunTraining` — un message qui
ne porte rien — chaque jour à `phil_courses.training.run_at`, `06:00` par défaut, dans le fuseau horaire du site :

```sh
php bin/console messenger:consume scheduler_phil_courses_training
```

`phil_courses.training.schedule: false` laisse le calendrier de côté ; `phil:courses:training` l'exécute plutôt depuis
cron, et dit combien de membres il a joints, de rappels il a envoyés et d'achèvements il a renouvelés, et quelles
assignations il a sautées parce que leurs membres n'ont pu être nommés.

## Recertification

Une assignation avec `renew_months` renouvelle chaque achèvement : l'achèvement est renouvelé à la prochaine occurrence
d'une règle mensuelle de cet intervalle à partir de l'achèvement, lue par phil/recurrence — le même jour du mois, à la
même heure ; un achèvement un jour qu'un mois n'a pas, le 31, est renouvelé au prochain mois qui l'a, comme la RFC 5545
compte une règle mensuelle.

Quand vient le renouvellement, l'exécution ouvre le cycle suivant : dû `renewal_grace_days` jours après le
renouvellement, 30 par défaut, ses rappels envoyés de nouveau. L'inscription de l'apprenant recommence — sa progression
dans chaque activité oubliée, ses énoncés `satisfied` libres d'être émis de nouveau, son achèvement défait, son
enregistrement gardé — et il est averti par `courses.training.renewed`. `EnrollmentRenewed` et `ObligationRenewed` sont
envoyés. Le relevé garde chaque achèvement.

## Cours équivalents

Un cours assigné dans une langue est rempli par tout cours de son [groupe de cours équivalents](equivalent-courses.md) :
`TrainingCompletions` termine les obligations sous le cours terminé et sous chacun de ses équivalents, et un membre
atteint par une assignation qui a déjà terminé un cours équivalent commence terminé — au plus récent de ces achèvements,
qui fixe le renouvellement d'une recertification. Une application assigne le cours de sa langue principale ; chaque
apprenant suit celui de la sienne.

## Événements

| Événement | Porte |
|---|---|
| `TrainingAssigned` | l'assignation, le cours |
| `ObligationOverdue` | l'assignation, l'apprenant, le cours |
| `ObligationRenewed` | l'assignation, l'apprenant, le cours |
| `EnrollmentRenewed` | l'inscription, le cours, l'apprenant |
| `CreditsGranted` | l'entrée du relevé, l'apprenant, le cours |

## Configuration

| Option | Défaut | Ce qu'elle fait |
|---|---|---|
| `training.enabled` | `true` | La formation obligatoire, ses pages, ses commandes, son calendrier et son module de vie privée. |
| `training.reminder_days` | 7 | Combien de jours avant l'échéance un cours est bientôt dû et son premier rappel envoyé, de 1 à 90. |
| `training.renewal_grace_days` | 30 | Combien de jours après son renouvellement une recertification est de nouveau due, de 1 à 365. |
| `training.max_members` | 10 000 | Combien de membres d'un groupe ou d'une unité une assignation joint. |
| `training.run_at` | `06:00` | L'heure de l'exécution quotidienne du calendrier, `HH:MM` dans le fuseau horaire du site. |
| `training.schedule` | `true` | Si le calendrier `phil_courses_training` s'exécute, avec Symfony Scheduler. |
| `training.manager_role` | `ROLE_COURSES_MANAGER` | Le rôle qu'un gestionnaire détient pour voir la formation de chaque apprenant et leurs relevés. |
| `training.notifier` | `null` | Un service `TrainingNotifier` ; les notifications de phil/notifications-bundle quand il vaut null et qu'il est enregistré, aucune sinon. |
| `training.keep_transcripts` | `false` | Si les relevés survivent à une demande d'effacement ; voir [crédits et relevés](credits-and-transcripts.md#vie-privée). |
