# Deactivation

| Type | Kind | Role |
|---|---|---|
| `Contract\Deactivatable` | interface | `isActive()`, `deactivatedAt()`, `deactivate()`, `reactivate()`, `stampDeactivated()` |
| `DeactivatableEntity` | trait | The columns `active`, boolean, and `deactivated_at`, `datetime_immutable`, nullable |
| `DeactivationListener` | listener: `prePersist`, `onFlush` | Keeps the date in step with the state, in UTC |
| `DeactivatedFilter` | SQL filter `phil_deactivated` | `active = true` on every `Deactivatable` entity |

A deactivated record is switched off, not removed: its row stays whole, its relations stay, and it comes back with
one call. Soft delete says a record is gone and a second removal deletes it; deactivation says nothing of the kind, and
the two can be combined on one entity.

```php
$member->deactivate();
$manager->flush();                                 // active = false, deactivated_at = now

$manager->getFilters()->disable(DeactivatedFilter::NAME);
$member = $members->find($id);
$member->reactivate();
$manager->flush();                                 // active = true, deactivated_at = null
```

| Flush | `deactivated_at` |
|---|---|
| an entity switched off | set to now, unless it was given a date |
| an entity switched back on | forgotten |
| an entity persisted already off | set to now, unless it was given a date |

An import that keeps an original date calls `stampDeactivated()` itself, and the listener leaves it alone.

The filter hides deactivated rows from every query, lazily loaded collections included, while it is enabled. An
administration screen disables it for its queries. A to-one relation to a deactivated row meets what a relation to a
soft-deleted row meets: see [Soft delete](soft-delete.md#relations-to-a-deleted-row).

The filter compares the column with the database's own spelling of true: `1` on MySQL and SQLite, `true` on PostgreSQL.
