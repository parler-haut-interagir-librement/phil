# Testing

| Area | What is covered |
|---|---|
| `Announcements`, `Release`, `News` | Notes and news by migration, the order of the run, locales, what is refused, clearing |
| `Channel`, `Messages` | Notes and news of a locale, headings and links, titles numbered and their room, the cut at a space, lengths in characters, the bounds of a channel |
| `Announcer`, `ChatterPublisher` | Every channel told, a failing one logged once and left, the transport of the chat message |
| `AnnouncingMigrationFactory`, `AnnouncingMigrator`, `AnnouncingMigration` | Real Doctrine migrations on SQLite in memory: one announcement per run, a skipped migration and a plain one, a dry run, a failed run with and without all or nothing, nothing to run, notes left by an earlier attempt, a migration not given the announcements |

```sh
vendor/bin/phpunit packages/migration/tests
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/migration/tests/Benchmark
```
