# Output

## Timestamps

`--timestamps` puts the time before each line the command writes, on the standard and the error output:

```sh
bin/console app:import --timestamps
bin/console app:import --timestamps=relative
```

```text
[2026-03-14T09:26:53.589+00:00] Importing 1200 rows
[+12.345s] Imported
```

| Value | Time |
|---|---|
| none | The configured format, `iso` by default |
| `iso` | The moment in ISO 8601, to the millisecond, in the application's time zone |
| `relative` | The seconds since the command started, to the millisecond |
| `none` | No time, even when the configuration enables it |

The time is added to the bytes written to the stream, after the formatter has done its work:

- a line written in several parts gets one time, at its start;
- the widths of tables, titles and blocks stay those of their text;
- a message the verbosity hides never reaches the stream, and gets no time;
- the time is grey on a decorated output, plain otherwise.

An output that writes to no stream, such as a `BufferedOutput`, gets no time. The guards' question and notice come
before the timestamps are attached, and have none.

## Debug logs

With Monolog, `--debug-phil` writes the logs of chosen channels, from the debug level up, to the error output, whatever
the verbosity of the handlers the application configures:

```sh
bin/console app:import --debug-phil=doctrine,http_client
bin/console app:import --debug-phil
```

Without a value, the option prints the channels of `debug.channels`, or every channel when that list is empty. The
channels are those of MonologBundle: `app` for `monolog.logger`, and the name of each `monolog.logger.<channel>`. A
channel the container does not have is reported on the error output; the others are printed. The messages are written as
raw text, never read as console markup. The handlers are taken off the loggers when the command ends.

## Summary

At the end of a command, with `-v` by default, the error output gets one line:

```text
app:import: exit code 0 in 12.345 s, memory +3.2 MiB, peak 48.0 MiB.
```

The duration runs from the end of the guards to the end of the command; the memory is what PHP's scripts use more, or
less, than at the start; the peak is the process's, since it started. `summary: always` writes the line at every
verbosity, `summary: never` never.

## The options of a command

The three options are added to the command that runs, not to the application: an application option would clash with a
command that defines an option of the same name its own way. Such an option stays the command's, and the bundle leaves
it alone; `timestamps.enabled` still applies to it.
