# Changelog

All notable changes to `phil/console-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## NEXT - YYYY-MM-DD

### Added

- `Domain\Attribute\ForbidEnvironment`: a repeatable attribute of command classes and methods naming the kernel
  environments a command refuses to run in, at least one, each name checked.
- `Domain\Attribute\ConfirmEnvironment`: a repeatable attribute of command classes and methods naming the environments
  that ask for a confirmation, every environment without a name.
- `Domain\ValueObject\EnvironmentGuards`: the guards of one command, the environments of every attribute added up.
- `Domain\ValueObject\ConsoleSettings`, `Domain\TimestampFormat` (`iso`, `relative`) and `Domain\SummaryMode`
  (`always`, `verbose`, `never`).
- `Domain\ValueObject\RunSummary`: a command's exit code, duration, memory added and peak, as one line.
- `Domain\Exception\ConsoleException`, `InvalidGuard` and `EnvironmentRefused`, whose code 77 is the exit code of a
  refused command.
- `Application\Contract\DebugSwitch` and `MemoryGauge`: the ports of `--debug-phil` and of the summary's memory.
- `Application\EnvironmentGuard`: a command refused where it is forbidden, the environment's name asked where it needs a
  confirmation, a non-interactive run refused there unless `--force-environment` is given.
- `Infrastructure\Console\CommandGuardReader`: the attributes of a command read on the class that holds its code and
  on the method it runs, lazy and traced commands unwrapped.
- `Infrastructure\Console\ConsoleOptions`: `--timestamps`, `--force-environment` and `--debug-phil` added to the
  running command, an option the command defines its own way left to it.
- `Infrastructure\Console\StreamStamps` and `LineStamper`: the time before each line of the standard and error streams,
  added to their bytes by a stream filter, grey on a decorated output.
- `Infrastructure\EventListener\ConsoleListener`: the options, the guards, the timestamps and the debug logs before a
  command, the summary after it, what a run attached detached at its end or on reset.
- `Infrastructure\Monolog\ChannelDebugSwitch` and `OutputHandler`: the debug logs of Monolog channels written to the
  error output as raw text for the run.
- `Infrastructure\Command\LintGuardsCommand`: `lint:console-guards`, the guarded commands as a table or JSON, a failure
  when a command asks for a confirmation where it is forbidden.
- `Infrastructure\Runtime\PhpMemoryGauge` and `SystemClock`.
- `Infrastructure\DependencyInjection\LoggerChannelsPass`: the Monolog channels of the container given to the debug
  switch, the switch removed without a channel.
- `PhilConsoleBundle` and the `phil_console` configuration: the timestamps, the summary and the debug channels.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The benchmark of the listener and of the stamping.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
