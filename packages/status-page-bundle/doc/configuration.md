# Configuration

```yaml
phil_status_page:
    title: Example status            # the title of the page, its feed and its snapshot
    source: auto                     # auto | monitoring | declared
    source_service: ~                # a ComponentStatuses of the application, instead of the source
    clock: clock                     # the PSR-20 clock the page is dated by
    history_days: 90                 # the days of resolved incidents shown, 0 to 366
    cache_max_age: 60                # the seconds browsers and shared caches keep the answers; 0 = ask each time
    layout: '@PhilStatusPage/layout.html.twig'   # the layout of the served page: blocks "title" and "content"
    kit_subdirectory: ~              # the components subdirectory of the kit's recipes; the Toolkit's when null
    monitoring:
        cache_ttl: 60                # the seconds the states read from the checks are kept; 0 = run them each view
        cache_pool: cache.app
    components:
        website:                     # the id: 1 to 64 lower-case letters, digits, "-" or "_"
            name: Website
            description: The public pages
            group: ~                 # the heading the component is shown under
            status: operational      # its state when no check says otherwise
            checks: [database]       # checks of phil/monitoring-bundle
            tasks: []                # scheduled tasks of phil/monitoring-bundle
    records:
        service: ~                   # a StatusRecords of the application
        file: ~                      # a JSON file read on each page
        incidents: []                # used when neither service nor file is given
        maintenances: []
    snapshot:
        base_url: ~                  # the absolute address the snapshot is read at; required with a target
        stylesheet: ~                # an absolute address of a stylesheet the snapshot links to
        schedule: ~                  # such as "5 minutes": the schedule phil_status_page
        targets:
            mirror: { type: directory, path: /mnt/status }
            bucket: { type: flysystem, storage: status.storage, prefix: '', visibility: public }
            own: { type: service, service: app.status_publisher }
```

## Source of the states

| `source` | The state of a component |
|---|---|
| `auto` | From `phil/monitoring-bundle` when its bundle is registered, else as declared. |
| `monitoring` | From `phil/monitoring-bundle`; the container fails to build without it. |
| `declared` | Its `status`, as the configuration declares it. |

`source_service` replaces the source with a service implementing `ComponentStatuses`. [The page](page.md) explains how
checks, tasks, maintenances and incidents make a state.

## Records

`records.service` wins over `records.file`, which wins over the lists `incidents` and `maintenances`. The lists are
checked when the container is built: a record that does not read fails it, with the record and the field named. The
format is in [Incidents and maintenances](records.md).

## Snapshot

`base_url` is read by `phil/url`: an absolute `http` or `https` address on a public host. It is required as soon as a
target is declared, since the JSON and the feed of a snapshot carry absolute addresses; `schedule` requires a target.
A `directory` target names its `path`, a `flysystem` target its `storage` — the service id of a Flysystem
`FilesystemOperator` —, a `service` target its `service` — a `SnapshotPublisher`. Target names are kept as written,
dashes included. See [Snapshot](snapshot.md).
