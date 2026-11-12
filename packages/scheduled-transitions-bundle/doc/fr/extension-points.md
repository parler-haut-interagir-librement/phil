<!-- phil-dev source-sha256=4455ae8a4c18c094bb918c9eb8d778262727f1de6e4a4106cc07cbe9aa306fcc -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise. Les ressources sont des références de `phil/resource`.

## Ports

### `TransitionHandler`

`Phil\ScheduledTransitionsBundle\Application\TransitionHandler` applique une transition planifiée. Un service qui
l'implémente reçoit l'étiquette `phil_scheduled_transitions.handler` par configuration automatique ; le premier
gestionnaire qui prend en charge une transition l'applique, et `before` ou `after` dans l'étiquette place ceux de
l'application avant ou après `WorkflowTransitionHandler`. Voir [Gestionnaires](handlers.md).

| Méthode | Contrat |
|---|---|
| `supports(ResourceReference $resource, string $transition): bool` | S'il applique cette transition à cette ressource |
| `apply(ResourceReference $resource, string $transition): void` | L'applique et enregistre ; lance une exception quand il ne le peut pas, et la tentative est comptée et retentée jusqu'à `max_attempts` |

Une exécution qui dure plus longtemps que `claim_for` laisse une autre exécution reprendre la transition : un
gestionnaire est écrit de sorte qu'appliquer une transition déjà appliquée laisse la ressource telle qu'elle est.

### `SubjectResolver`

`Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver` trouve, avec `symfony/workflow`, le sujet que
le workflow déplace. Un service qui l'implémente reçoit l'étiquette `phil_scheduled_transitions.subject_resolver` par
configuration automatique.

| Méthode | Contrat |
|---|---|
| `resolve(ResourceReference $resource): ?object` | Le sujet, ou `null` pour une référence qu'il ne connaît pas |
| `saved(object $subject): void` | Enregistre le sujet une fois déplacé |

### `ScheduledTransitionRepository`

`Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository` contient les transitions planifiées ;
c'est un alias public de `DoctrineScheduledTransitionRepository`. Un stockage propre à l'application garde `claim()`
atomique, pour que deux exécutions n'appliquent jamais une même transition à la fois, et `due()` borné par sa limite.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `frequency` | La fréquence à laquelle Symfony Scheduler applique les transitions échues |
| `batch` | Combien une exécution en applique au plus |
| `max_attempts` | Combien de fois une transition qui échoue est tentée |
| `claim_for` | Combien de temps une exécution retient une transition qu'elle applique |

## Exécution

`phil:scheduled-transitions:run` depuis un *cron*, ou le calendrier que `TransitionsSchedule` enregistre auprès de
Symfony Scheduler : voir [Exécution](running.md). `Scheduler` est le service avec lequel l'application planifie des
transitions : voir [Planification](scheduling.md).
