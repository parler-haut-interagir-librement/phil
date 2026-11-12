<!-- phil-dev source-sha256=7cfb1987d34d99bd8b031ff5e7a89aa894b0ffbb8f2bd29a8ed998762cfb1a0c -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toute | Une erreur gardée à 255 caractères |
| `doctrine/orm` | ^3.7 | La table des transitions |
| `psr/clock` | ^1.0 | Ce qui est échu ; le service `clock` du conteneur, que FrameworkBundle enregistre une fois `symfony/clock` installé |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | La classe du bundle, sa configuration, les identifiants |
| `phil/resource` | ^0.1 | `ResourceReference`, la sorte et l'identifiant d'une ressource |
| `symfony/scheduler`, `symfony/messenger` | facultatif | Les exécutions depuis Symfony Scheduler |
| `symfony/workflow` | facultatif | Les transitions appliquées par un workflow |
| `symfony/console` | facultatif | `phil:scheduled-transitions:run` |

```sh
composer require phil/scheduled-transitions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_scheduled_transitions.yaml
phil_scheduled_transitions:
    frequency: '1 minute'   # how often Symfony Scheduler runs what is due
    batch: 100              # transitions a run applies at most
    max_attempts: 3         # tries before a failing transition is given up
    claim_for: 600          # seconds a run holds a transition it applies
```

Avec DoctrineBundle, le bundle associe son entité ; les migrations de l'application créent `phil_scheduled_transition` :
`id`, `resource_kind`, `resource_id`, `transition` (64), `due_at` (UTC), `status` (`pending`, `done`, `failed`,
`cancelled`), `attempts`, `last_error` (255), `applied_at`, `claimed_until` (UTC, la réservation de l'exécution qui
l'applique), indexée sur le statut et le moment, et sur la ressource.
