# Retention

A trashed resource is kept `retention` days — or its kind's, in `retention_by_kind` —, counted from the moment it
was trashed; `TrashItem::purgeAfter()` says until when.

```bash
bin/console phil:trash:purge --limit=500   # every night, from the scheduler
```

`Trash::purge($limit)` takes the oldest expired items first, asks each one's handler to forget what it left outside
— files —, and deletes it. An item whose kind no longer has a handler is deleted all the same.

## Events

| Event | When | Carries |
|---|---|---|
| `ResourceTrashed` | A resource went to the trash | The `TrashItem` |
| `ResourceRestored` | It came back | The `TrashItem` and the resource |
| `TrashItemPurged` | It left for good | The `TrashItem` |

They are dispatched by the application's event dispatcher, when there is one: an audit trail listens to them.
