# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports written by the application or another package

### `DebugSwitch`

`Phil\ConsoleBundle\Application\Contract\DebugSwitch` — what `--debug-phil` turns on. Replace the service
`phil_console.debug_switch` to print another logger's records, or to raise the level of another logging library; tag
it `kernel.reset` when it keeps what it enabled.

| Method | Contract |
|---|---|
| `channels(): list<string>` | The channels it knows, in order |
| `enable(list<string> $channels, OutputInterface $output): void` | Writes the records of those channels to the output; skips the unknown ones |
| `disable(): void` | Undoes enable(); does nothing when nothing is enabled |

```yaml
services:
    phil_console.debug_switch:
        class: App\Console\AuditLogSwitch
        tags: [kernel.reset]
```

### `MemoryGauge`

`Phil\ConsoleBundle\Application\Contract\MemoryGauge` — the memory of the summary. Replace the service
`phil_console.memory_gauge`.

| Method | Contract |
|---|---|
| `usage(): int` | The bytes in use now |
| `peak(): int` | The highest bytes in use |

## Services

| Service | Role |
|---|---|
| `phil_console.clock` | The `Psr\Clock\ClockInterface` of the timestamps and the summary; replace it with the application's clock |
| `Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader` | The guards of a command, for a tool of the application |
| `Phil\ConsoleBundle\Application\EnvironmentGuard` | Holds a command to its guards in the kernel's environment |

```yaml
services:
    phil_console.clock: '@clock'
```

## Events

The bundle dispatches no event of its own. A refusal is an `EnvironmentRefused` exception, which the console
dispatches with `console.error`; a listener of that event can log it or alert on it.

## Exceptions

| Exception | When |
|---|---|
| `Phil\ConsoleBundle\Domain\Exception\EnvironmentRefused` | A command forbidden, not confirmed, or not interactive where a confirmation is needed; code 77 |
| `Phil\ConsoleBundle\Domain\Exception\InvalidGuard` | An attribute without the environment it needs, or with an empty name |

Both implement `Phil\ConsoleBundle\Domain\Exception\ConsoleException`.
