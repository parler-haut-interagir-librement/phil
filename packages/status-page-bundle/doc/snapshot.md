# Snapshot

A snapshot is the static copy of the page at one moment, read where the site cannot take it down with it:

| File | Contents |
|---|---|
| `status.json` | The JSON of [the page](page.md), its addresses under `snapshot.base_url`. |
| `feed.atom` | The Atom feed, its ids and links under `snapshot.base_url`. |
| `index.html` | The page in a document of its own, linking to the two files beside it. |

A publisher writes them in this order, so the HTML that links to the others comes last. The HTML carries no script and
no address of the site; it is marked `noindex`, links the feed and the JSON as alternates, and carries a few rules for
the classes of the kit's recipes — or links `snapshot.stylesheet` instead, an absolute address on another host.

## Targets

| `type` | Publisher | Writes |
|---|---|---|
| `directory` | `DirectoryPublisher` | Into `path`: a mount of another machine, the root of a server that does not run the application, a folder a synchronisation copies away. Each file goes to a temporary file, then is renamed. |
| `flysystem` | `FlysystemPublisher` | Into the Flysystem storage `storage`, under `prefix`, each file with its media type and, when given, `visibility`: an object storage served as a website, a server reached by SFTP. |
| `service` | The application's | Whatever the `SnapshotPublisher` does: a hosting service is an adapter of the application, written against this port. |

The machine the snapshot is read from must not depend on what it describes: another host, another provider, another
network.

## Pushing

```sh
php bin/console phil:status-page:push           # every target
php bin/console phil:status-page:push mirror    # one target
```

The page is put together once and given to each target in the order they were declared. A target that fails does not
stop the others: the failure is logged at the `error` level with its exception, the command prints the short name of
the exception — never its message, which may carry a host or a credential — and exits with 1. An unknown target exits
with 2 before anything is written.

`PushStatusSnapshot` asks a worker to push to one target, or to every target; it carries the target's name only. Its
handler returns the `PushReport` and does not throw for a failed target, so one unreachable target does not hold the
others in a retry loop: the next message pushes again.

With `snapshot.schedule`, the schedule `phil_status_page` sends a `PushStatusSnapshot` at that frequency:

```sh
php bin/console messenger:consume scheduler_phil_status_page
```

A deployment, the end of an incident or a change of the records file may also push at once, by the command or by
dispatching the message.
