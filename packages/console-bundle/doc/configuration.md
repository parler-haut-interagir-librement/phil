# Configuration

```yaml
phil_console:
    timestamps:
        enabled: false        # true: every command writes the time without --timestamps
        format: iso           # iso, the moment to the millisecond, or relative, the seconds since the start
    summary: verbose          # always, verbose (with -v) or never
    debug:
        enabled: true         # false removes --debug-phil
        channels: []          # the channels --debug-phil prints when it names none; empty for every channel
```

| Option | Default | Effect |
|---|---|---|
| `timestamps.enabled` | `false` | Writes the time before each line of every command, as if `--timestamps` were given |
| `timestamps.format` | `iso` | The format of the time when `--timestamps` names none |
| `summary` | `verbose` | When the duration and memory summary is written |
| `debug.enabled` | `true` | Whether `--debug-phil` exists; it needs Monolog and at least one channel |
| `debug.channels` | `[]` | The channels of `--debug-phil` without a value |

An unknown format or summary mode, or an empty channel name, fails the container build.

The kernel environment the guards compare with is `%kernel.environment%`.
