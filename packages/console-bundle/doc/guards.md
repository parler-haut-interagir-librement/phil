# Guarding commands

## Forbidding an environment

`#[ForbidEnvironment]` names the kernel environments a command refuses to run in:

```php
#[AsCommand('app:database:reset')]
#[ForbidEnvironment('prod', 'staging')]
final class ResetDatabase
{
    public function __invoke(): int
}
```

In `prod`, the command does not run: the console prints
`The command "app:database:reset" cannot run in the "prod" environment.` and exits with code 77. No option lifts the
refusal, not even `--force-environment`.

## Asking for a confirmation

`#[ConfirmEnvironment]` asks for the environment's name before the command runs:

```php
#[AsCommand('app:customers:anonymize')]
#[ConfirmEnvironment('prod')]
final class AnonymizeCustomers
```

```text
The command "app:customers:anonymize" runs in the "prod" environment. Type "prod" to continue: prod
```

| Situation | Result |
|---|---|
| The answer is the environment's name, spaces around it aside | The command runs |
| Another answer, an empty one, or the end of the input | Refused, exit code 77 |
| A non-interactive run (`--no-interaction`, a cron, a CI job) | Refused, exit code 77 |
| `--force-environment` | The command runs without asking; a line on the error output says so |

Without an environment, `#[ConfirmEnvironment]` asks in every environment. The question and the notice are written to
the error output, so that the standard output stays the command's.

## Where the attributes are read

Both attributes are repeatable, and the environments of every occurrence add up. They are read on:

- the class of an invokable command, and its `__invoke()` method;
- the class of a method-level command, and its method;
- the class of a command that extends `Command`, and its `__invoke()` method when it has one.

```php
#[ConfirmEnvironment('prod')]
final class Operations
{
    #[AsCommand('app:cache:rebuild')]
    public function rebuild(): int

    #[AsCommand('app:data:wipe')]
    #[ForbidEnvironment('prod')]
    public function wipe(): int
}
```

A lazy command is loaded to read its attributes, which happens only when it runs or is listed.

## The guards and the other listeners

The guards run in the bundle's listener of `console.command`. A refusal is thrown as `EnvironmentRefused`: the console
renders it as an error, dispatches `console.error` and `console.terminate`, and exits with its code, 77 (`EX_NOPERM` of
`sysexits.h`).

## Listing the guarded commands

```sh
bin/console lint:console-guards
bin/console lint:console-guards --format=json
```

```text
 ------------------------- -------------- -----------------
  Command                   Forbidden in   Confirmation in
 ------------------------- -------------- -----------------
  app:customers:anonymize                  prod
  app:database:reset        prod, staging
 ------------------------- -------------- -----------------
```

A confirmation in every environment is shown as `*`. The command fails, with code 1, when a command asks for a
confirmation in an environment it is forbidden in: one of the two attributes is then wrong.
