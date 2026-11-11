# Console

## `phil:feature-flags:list`

Lists the feature flags with their current configuration (`ListFlagsCommand`, over `FlagCatalog` and the
configured clock). Always registered.

```bash
bin/console phil:feature-flags:list
bin/console phil:feature-flags:list --expired
bin/console phil:feature-flags:list --fail-on-expired
```

| Option | Effect |
|---|---|
| *(none)* | Lists every flag; warns, and still exits 0, when some are past their expiry date. |
| `--expired` | Lists only the flags past their expiry date. |
| `--fail-on-expired` | Prints an error and exits 1 (`Command::FAILURE`) when any flag is past its expiry date. |

The two options combine.

### Columns

| Column | Content |
|---|---|
| Flag | The name. |
| State | `active` or `inactive`, the effective state; `(stored)` when it comes from the state store. |
| Kind | `release`, `experiment`, `ops`, `permission` or empty. |
| Targeting | `everyone` (no rule) or `N rule(s)`; for an experiment, `; A/B by <attribute>: <variant> <weight>%, …`. |
| Owner | The owner or empty. |
| Expires on | `YYYY-MM-DD`, followed by `(expired)` when past. |
| Source | The service id of the provider that declares the flag. |

Rows are sorted by name. Only **enumerable** providers contribute: a provider that only implements `FlagProvider`
(a remote service that answers one flag at a time) has no row ([extension-points.md](extension-points.md#enumerableflagprovider)).

### Unreadable flags

A flag whose definition its provider holds but cannot read (`FlagCatalog::unreadable()`) has no row; after the
table, a warning names each one with its source and reason, and the command still exits 0:

```text
 [WARNING] 1 flag cannot be read: broken-row (phil_feature_flags.provider.doctrine): The stored definition of
           "broken-row" is not JSON: Syntax error
```

### Expiry message

```text
 [WARNING] 1 flag is past its expiry date: forgotten.
 [ERROR] 2 flags are past their expiry date: forgotten, old-banner.
```

The second form is printed with `--fail-on-expired`. A flag is expired from midnight UTC of its expiry day
([metadata-and-expiry.md](metadata-and-expiry.md#expiry)).

### In CI

```bash
bin/console phil:feature-flags:list --fail-on-expired
```

The build goes red while a flag has outstayed its purpose, which turns a forgotten flag into a task rather than into
one more branch in the code. The listing reads the database store too when it is enabled, so in CI it needs the
same database configuration as the application, or reads only the configuration provider if `doctrine` is
disabled in the CI environment.

## Other commands

The bundle ships no command to toggle, create or edit a flag: changes go through the admin
or the application's own code calling `FlagToggler` and `FlagEditor` with an actor and a reason.
