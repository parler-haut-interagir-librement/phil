<!-- phil-dev source-sha256=6397bd3cab8dab7d4cfa8f45cd04409e88e765e8f626568354a43f7bdc194a77 -->
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

## Source des états

| `source` | L'état d'un composant |
|---|---|
| `auto` | Tiré de `phil/monitoring-bundle` quand son bundle est enregistré, sinon tel que déclaré. |
| `monitoring` | Tiré de `phil/monitoring-bundle` ; la construction du conteneur échoue sans lui. |
| `declared` | Son `status`, tel que la configuration le déclare. |

`source_service` remplace la source par un service qui implémente `ComponentStatuses`. [La page](page.md) explique
comment les vérifications, les tâches, les maintenances et les incidents font un état.

## Enregistrements

`records.service` l'emporte sur `records.file`, qui l'emporte sur les listes `incidents` et `maintenances`. Les listes
sont vérifiées à la construction du conteneur : un enregistrement qui ne se lit pas la fait échouer, en nommant
l'enregistrement et le champ. Le format est décrit dans [Incidents et maintenances](records.md).

## Instantané

`base_url` est lue par `phil/url` : une adresse `http` ou `https` absolue sur un hôte public. Elle est obligatoire dès
qu'une cible est déclarée, puisque le JSON et le flux d'un instantané portent des adresses absolues ; `schedule` exige
une cible. Une cible `directory` nomme son `path`, une cible `flysystem` son `storage` — l'identifiant de service d'un
`FilesystemOperator` de Flysystem —, une cible `service` son `service` — un `SnapshotPublisher`. Les noms de cibles sont
conservés tels qu'écrits, tirets compris. Voir [Instantané](snapshot.md).
