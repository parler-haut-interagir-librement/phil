# Testing

## The bundle's tests

The suite `tests/Unit` tests:

| Area | What is tested |
|---|---|
| `ProcessCommandRunner` | tools found on the `PATH` or where the configuration says, a missing tool named with the configuration key; an environment, an input file and the output; a failure with its error output; a timeout |
| `DatabaseClients` | the client of each platform from its parameters, without connecting; an in-memory SQLite database and another platform refused |
| `Console` | the three commands on a SQLite file with the real `sqlite3`: a set created then the expired ones removed, labels and their refusal; every refusal of a restore, production with and without the right allowance, the name asked for interactively, the backup taken first and what it holds, the latest three kept, media pruned on demand, a failed decryption; restore tests on SQLite and on a recorded MySQL client with and without a scratch database |
| `PhilBackupBundle` | the extension loaded on an uncompiled `ContainerBuilder`: a configuration without a key refused unless allowed, the defaults, every key reaching its service, each build-time error; a compiled container that builds the client without connecting |

From the monorepo root:

```bash
vendor/bin/phpunit packages/backup-bundle/tests
```

The console tests need the `sqlite3` command line tool on the `PATH`. The bundle is held to 100 % covered mutation
score by Infection.

## Testing your configuration

Boot the kernel in the test environment and run `phil:backup:create` then `phil:backup:verify` against a SQLite
database: both succeed when the configuration, the key and the media directories are right. To test a restore,
pass `--confirm --database=<name>` and run the console without interaction.
