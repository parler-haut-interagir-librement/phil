# Console

`phil:accessibility-statement:generate` writes the next version of the statement from the latest audit, with the
`StatementGenerator` of `phil/accessibility-statement`, and publishes it. It is registered when `audit` is enabled.

```sh
bin/console phil:accessibility-statement:generate
bin/console phil:accessibility-statement:generate --dry-run
bin/console phil:accessibility-statement:generate --format=json
```

| Option | Means |
|---|---|
| `--dry-run` | Shows the next version without publishing it. |
| `--format` | `text`, a summary — target, status, evaluation and a table of the non-conformities —, or `json`, the statement as the data the configuration takes. |

With the `config` source, the command cannot publish: it shows the next version, which is written in the
configuration by hand — `--format=json` gives it.

| Exit code | When |
|---|---|
| 0 | the version is published, or shown |
| 1 | no statement is published yet, or no audit is there to read |
| 2 | a report cannot be read, the version cannot be published, or the format is unknown |

## The audit directory

By default the audit is the directory `var/accessibility`: one JSON report of `phil/accessibility-check` per page,
named after it, such as

```sh
bin/console phil:accessibility:check templates/pages/home.html --format=json > var/accessibility/home.json
bin/console phil:accessibility:check templates/pages/events.html --format=json > var/accessibility/events.json
bin/console phil:accessibility-statement:generate
```

The audit is dated by the newest report; its method is `audit.method`, its report `audit.report_url`. A file whose name
starts with a dot, a file that does not end with `.json` and a subdirectory are left alone; a directory of more than
1 000 reports, or a report larger than 5 MiB, is refused before it is read. An audit that checks the pages some other
way gives an `AuditSource` service of its own ([Extension points](extension-points.md)).
