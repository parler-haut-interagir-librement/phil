# Testing

| Area | What is covered |
|---|---|
| `TrashItem`, `ResourceReference`, the exceptions | Every check and bound, times in UTC, a parent with a colon in its identifier, the mapping |
| `Trash` | Trashing and restoring, a parent that is gone, retention by kind, the purge in order, events, no clock or dispatcher |
| `PurgeTrashCommand` | Limits, refused limits |
| `PhilTrashBundle` | Services, autoconfiguration, configuration, the mapping |
| `BinaryKeysListener` | The keys in `utf8mb4_bin` in the schema generated for MySQL and MariaDB, the default collation on SQLite, the options a field already has kept |

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: four resources whose identifiers differ only by case or accent are each found by their own identifier only, and on
MySQL the keys are in `utf8mb4_bin` and the tables of the previous version reach it through the schema diff. Each
server's test reads its URL from `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/trash-bundle/tests/Integration
```

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, an `Article` entity and its handler:
`TrashBundleTest` trashes, lists, restores under another parent, and purges once a moved clock passes the retention.

```sh
vendor/bin/phpunit packages/trash-bundle/tests
```
