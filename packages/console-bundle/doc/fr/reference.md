<!-- phil-dev source-sha256=68607819ae3a1726f7470076dc0b404663f395e7e533610e9ad0f2d599fcd813 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Attribute\ForbidEnvironment` | Les environnements où une commande refuse de s'exécuter |
| `Domain\Attribute\ConfirmEnvironment` | Les environnements qui demandent une confirmation ; tous, sans nom |
| `Domain\ValueObject\EnvironmentGuards` | Les gardes d'une commande, additionnées |
| `Domain\ValueObject\ConsoleSettings` | La configuration de la sortie |
| `Domain\ValueObject\RunSummary` | La ligne de sommaire d'une commande |
| `Domain\TimestampFormat` | `iso`, `relative` |
| `Domain\SummaryMode` | `always`, `verbose`, `never` |
| `Domain\Elapsed` | Les secondes entre deux moments |
| `Domain\Exception\ConsoleException` | Le marqueur des exceptions du bundle |
| `Domain\Exception\InvalidGuard` | Un attribut mal écrit |
| `Domain\Exception\EnvironmentRefused` | Une commande refusée, code 77 |

## Application

| Classe | Rôle |
|---|---|
| `Application\Contract\DebugSwitch` | Le port de `--debug-phil` |
| `Application\Contract\MemoryGauge` | Le port de la mémoire du sommaire |
| `Application\EnvironmentGuard` | Soumet une commande à ses gardes |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Console\CommandGuardReader` | Les gardes d'une commande, lues par réflexion |
| `Infrastructure\Console\ConsoleOptions` | Les options ajoutées à la commande qui s'exécute |
| `Infrastructure\Console\StreamStamps` | Attache et détache les horodatages des flux d'une sortie |
| `Infrastructure\Console\LineStamper` | L'heure devant chaque ligne d'un flux |
| `Infrastructure\Console\TimestampFilter` | Le filtre de flux de l'horodateur |
| `Infrastructure\EventListener\ConsoleListener` | L'écouteur de `console.command` et de `console.terminate` |
| `Infrastructure\Command\LintGuardsCommand` | `lint:console-guards` |
| `Infrastructure\Monolog\ChannelDebugSwitch` | L'interrupteur de débogage des canaux Monolog |
| `Infrastructure\Monolog\OutputHandler` | Le gestionnaire qui écrit les enregistrements dans une sortie de console |
| `Infrastructure\Runtime\PhpMemoryGauge` | La mémoire de PHP |
| `Infrastructure\Runtime\SystemClock` | L'heure du système |
| `Infrastructure\DependencyInjection\ConsoleServices` | Les services de la configuration |
| `Infrastructure\DependencyInjection\LoggerChannelsPass` | Les canaux Monolog donnés à l'interrupteur |
| `PhilConsoleBundle` | Le bundle et sa configuration |

## Options

| Option | Effet |
|---|---|
| `--timestamps[=iso\|relative\|none]` | L'heure devant chaque ligne |
| `--force-environment` | Exécute sans rien demander une commande qui demande une confirmation |
| `--debug-phil[=channel,...]` | Les journaux de débogage des canaux Monolog |
