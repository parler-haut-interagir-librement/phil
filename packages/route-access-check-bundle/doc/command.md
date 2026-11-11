# The command

```sh
bin/console phil:route-access:check [--format=text|json|github|junit] [--baseline=FILE] [--generate-baseline[=FILE]] [--only-problems] [--strict]
```

| Option | Meaning |
|---|---|
| `--format`, `-f` | The format of the report on the standard output: `text` (default), `json`, `github`, `junit`, or a format of the application. |
| `--baseline`, `-b` | The baseline file whose failures are tolerated; by default the configured one. |
| `--generate-baseline` | Writes the current failures to this file, or to the configured baseline, or to `route-access-baseline.json`, then exits with 0. |
| `--only-problems` | Leaves out the routes that pass. |
| `--strict` | Fails on warnings too: `access_control` entries that reach no route, stale baseline entries, inexact sample paths. |

The report goes to the standard output, raw, so that `--format=json > report.json` writes a valid document. The
messages about the command itself — an unknown format, an unreadable baseline, the baseline written — go to the error
output.

## Exit codes

| Code | When |
|---|---|
| 0 | Every route passes, or its failure is tolerated by the baseline; with `--strict`, no warning either. |
| 1 | A route is uncovered or conflicts, and the baseline does not tolerate it; with `--strict`, or a warning remains. |
| 2 | The format is unknown, or the baseline cannot be read: missing, larger than 5 MB, or not a baseline document. |

## In continuous integration

```yaml
# .github/workflows/ci.yaml
- name: Route access
  run: bin/console phil:route-access:check --env=test --format=github --only-problems
```

The GitHub format annotates each failing route on its controller's file and line. For a CI server that reads JUnit:

```sh
bin/console phil:route-access:check --env=test --format=junit > var/route-access.xml
```

## Adopting the check

On an application with many routes, generate the baseline once, commit it, and let the check fail only on new
failures:

```sh
bin/console phil:route-access:check --env=test --generate-baseline
```

Fix the tolerated routes one by one; `--strict` then reports each baseline entry already fixed, so that it is removed
and the baseline only shrinks.
