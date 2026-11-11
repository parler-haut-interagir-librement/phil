# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Attribute\ForbidEnvironment` | The environments a command refuses to run in |
| `Domain\Attribute\ConfirmEnvironment` | The environments that ask for a confirmation; all without a name |
| `Domain\ValueObject\EnvironmentGuards` | The guards of one command, added up |
| `Domain\ValueObject\ConsoleSettings` | The configuration of the output |
| `Domain\ValueObject\RunSummary` | The summary line of a command |
| `Domain\TimestampFormat` | `iso`, `relative` |
| `Domain\SummaryMode` | `always`, `verbose`, `never` |
| `Domain\Elapsed` | The seconds between two moments |
| `Domain\Exception\ConsoleException` | The marker of the bundle's exceptions |
| `Domain\Exception\InvalidGuard` | An attribute written wrong |
| `Domain\Exception\EnvironmentRefused` | A command refused, code 77 |

## Application

| Class | Role |
|---|---|
| `Application\Contract\DebugSwitch` | The port of `--debug-phil` |
| `Application\Contract\MemoryGauge` | The port of the summary's memory |
| `Application\EnvironmentGuard` | Holds a command to its guards |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Console\CommandGuardReader` | The guards of a command, read by reflection |
| `Infrastructure\Console\ConsoleOptions` | The options added to the running command |
| `Infrastructure\Console\StreamStamps` | Attaches and detaches the timestamps of an output's streams |
| `Infrastructure\Console\LineStamper` | The time before each line of one stream |
| `Infrastructure\Console\TimestampFilter` | The stream filter of the stamper |
| `Infrastructure\EventListener\ConsoleListener` | The listener of `console.command` and `console.terminate` |
| `Infrastructure\Command\LintGuardsCommand` | `lint:console-guards` |
| `Infrastructure\Monolog\ChannelDebugSwitch` | The debug switch of Monolog channels |
| `Infrastructure\Monolog\OutputHandler` | The handler that writes records to a console output |
| `Infrastructure\Runtime\PhpMemoryGauge` | PHP's memory |
| `Infrastructure\Runtime\SystemClock` | The system's time |
| `Infrastructure\DependencyInjection\ConsoleServices` | The services of the configuration |
| `Infrastructure\DependencyInjection\LoggerChannelsPass` | The Monolog channels given to the switch |
| `PhilConsoleBundle` | The bundle and its configuration |

## Options

| Option | Effect |
|---|---|
| `--timestamps[=iso\|relative\|none]` | The time before each line |
| `--force-environment` | Runs a command that asks for a confirmation without asking |
| `--debug-phil[=channel,...]` | The debug logs of Monolog channels |
