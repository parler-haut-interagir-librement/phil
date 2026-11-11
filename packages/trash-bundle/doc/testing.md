# Testing

| Area | What is covered |
|---|---|
| `TrashItem`, `ResourceReference`, the exceptions | Every check and bound, times in UTC, a parent with a colon in its identifier, the mapping |
| `Trash` | Trashing and restoring, a parent that is gone, retention by kind, the purge in order, events, no clock or dispatcher |
| `PurgeTrashCommand` | Limits, refused limits |
| `PhilTrashBundle` | Services, autoconfiguration, configuration, the mapping |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, an `Article` entity and its handler:
`TrashBundleTest` trashes, lists, restores under another parent, and purges once a moved clock passes the retention.

```sh
vendor/bin/phpunit packages/trash-bundle/tests
```
