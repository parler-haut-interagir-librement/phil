# phil/console-bundle

**Safer Symfony console commands**

phil/console-bundle guards the commands that must not run anywhere, or not without a second thought:
`#[ForbidEnvironment('prod')]` refuses a command in the listed kernel environments, and `#[ConfirmEnvironment('prod')]`
asks for the environment's name before it runs and refuses a non-interactive run unless `--force-environment` is given.
Every command gets `--timestamps`, which puts the time before each line it writes, and, with Monolog, `--debug-phil`,
which prints the debug logs of chosen channels. A summary of the command's duration, memory and exit code is written at
its end, and `lint:console-guards` lists the guarded commands. Nothing is kept between two commands: a long-running
process runs each one afresh.

## Status

| Part | State |
|---|---|
| `#[ForbidEnvironment]` and `#[ConfirmEnvironment]` on classes, `__invoke()` and method-level commands | Implemented and tested |
| `--force-environment` for non-interactive runs, exit code 77 on a refusal | Implemented and tested |
| `--timestamps` in ISO 8601 or relative to the start, or by configuration | Implemented and tested |
| `--debug-phil` with Monolog channels | Implemented and tested |
| The duration and memory summary | Implemented and tested |
| `lint:console-guards` as a table or JSON | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, worker mode. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Guarding commands](doc/guards.md) | The two attributes, the confirmation, the exit code, the lint command. |
| [Output](doc/output.md) | The timestamps, the debug logs, the summary. |
| [Extension points](doc/extension-points.md) | The debug switch, the memory gauge, the clock. |
| [Testing](doc/testing.md) | The bundle's tests and its benchmark. |
| [Reference](doc/reference.md) | Every class of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\ConsoleBundle\PhilConsoleBundle::class => ['all' => true],
```

```php
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:database:reset')]
#[ForbidEnvironment('prod', 'staging')]
final class ResetDatabase
{
    public function __invoke(): int
}

#[AsCommand('app:customers:anonymize')]
final class AnonymizeCustomers
{
    #[ConfirmEnvironment('prod')]
    public function __invoke(): int
}
```

```sh
bin/console app:customers:anonymize --timestamps=relative --debug-phil=doctrine -v
bin/console app:customers:anonymize --no-interaction --force-environment
bin/console lint:console-guards
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later; Monolog for `--debug-phil`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
