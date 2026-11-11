<!-- phil-dev source-sha256=1d4ebb8eaac22e38da1df3d4b684b781163ee45fc33ca14af197a616348da4c8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG indique comment.

## Ports écrits par l'application ou un autre paquet

### `DebugSwitch`

`Phil\ConsoleBundle\Application\Contract\DebugSwitch` — ce que `--debug-phil` active. Remplacez le service
`phil_console.debug_switch` pour afficher les enregistrements d'un autre journaliseur ou pour relever le niveau d'une
autre bibliothèque de journalisation ; donnez-lui l'étiquette `kernel.reset` s'il conserve ce qu'il a activé.

| Méthode | Contrat |
|---|---|
| `channels(): list<string>` | Les canaux qu'il connaît, dans l'ordre |
| `enable(list<string> $channels, OutputInterface $output): void` | Écrit les enregistrements de ces canaux dans la sortie ; ignore les canaux inconnus |
| `disable(): void` | Annule enable() ; ne fait rien si rien n'est activé |

```yaml
services:
    phil_console.debug_switch:
        class: App\Console\AuditLogSwitch
        tags: [kernel.reset]
```

### `MemoryGauge`

`Phil\ConsoleBundle\Application\Contract\MemoryGauge` — la mémoire du sommaire. Remplacez le service
`phil_console.memory_gauge`.

| Méthode | Contrat |
|---|---|
| `usage(): int` | Les octets utilisés maintenant |
| `peak(): int` | Le maximum d'octets utilisés |

## Services

| Service | Rôle |
|---|---|
| `phil_console.clock` | Le `Psr\Clock\ClockInterface` des horodatages et du sommaire ; remplacez-le par l'horloge de l'application |
| `Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader` | Les gardes d'une commande, pour un outil de l'application |
| `Phil\ConsoleBundle\Application\EnvironmentGuard` | Soumet une commande à ses gardes dans l'environnement du noyau |

```yaml
services:
    phil_console.clock: '@clock'
```

## Événements

Le bundle ne répartit aucun événement qui lui soit propre. Un refus est une exception `EnvironmentRefused`, que la
console répartit avec `console.error` ; un écouteur de cet événement peut la journaliser ou lancer une alerte.

## Exceptions

| Exception | Quand |
|---|---|
| `Phil\ConsoleBundle\Domain\Exception\EnvironmentRefused` | Une commande interdite, non confirmée ou non interactive là où une confirmation est nécessaire ; code 77 |
| `Phil\ConsoleBundle\Domain\Exception\InvalidGuard` | Un attribut sans l'environnement qu'il exige, ou avec un nom vide |

Les deux implémentent `Phil\ConsoleBundle\Domain\Exception\ConsoleException`.
