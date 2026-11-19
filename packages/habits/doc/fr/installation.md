<!-- phil-dev source-sha256=39ffa8cd4512d5e62fb81b8d5a2f12a8504a408252d128e168012c42f014a033 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` | Le nom d'une habitude, compté en caractères. |
| `psr/clock` | `^1.0` | Aujourd'hui, dans le fuseau horaire du membre, et l'instant d'une validation. |
| `psr/event-dispatcher` | `^1.0` | Les événements après une validation. |
| `doctrine/dbal` | `^4.5`, suggéré | `DbalHabitStore` et `DbalCheckInStore`, les dépôts en base de données. |

```sh
composer require phil/habits
```

## Les services

`Habits` prend un dépôt d'habitudes, un dépôt de validations, une horloge et, facultativement, un répartiteur
d'événements, un `ProgressCalculator`, les `Milestones` à célébrer et le nombre de jours dont une validation peut
remonter — sept par défaut, jusqu'à 366. Dans une application Symfony avec Doctrine :

```yaml
# config/services.yaml
services:
    Phil\Habits\Dbal\DbalHabitStore: ~
    Phil\Habits\Dbal\DbalCheckInStore: ~
    Phil\Habits\Contract\HabitStore: '@Phil\Habits\Dbal\DbalHabitStore'
    Phil\Habits\Contract\CheckInStore: '@Phil\Habits\Dbal\DbalCheckInStore'

    Phil\Habits\Milestones:
        arguments: { $streaks: [7, 30, 100], $strengths: [50, 80] }

    Phil\Habits\Habits:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'
            $backfillDays: 3
```

Les tables des dépôts en base de données s'ajoutent à un schéma par leur `configureSchema()` : voir
[Stockage](storage.md). Les dépôts en mémoire, `InMemoryHabitStore` et `InMemoryCheckInStore`, servent aux tests et aux
démonstrations.
