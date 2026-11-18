<!-- phil-dev source-sha256=dd8c5686e5f5d6e79446a49f610b2f789f1da776781d455990013868b1836a08 -->
# Configuration

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    scanner: clamd                      # clamd, fake, null, or the id of a Scanner service
    clamd:
        address: tcp://127.0.0.1:3310   # or unix:///run/clamav/clamd.ctl
        timeout: 30.0                   # each read or write, in seconds
        connect_timeout: 5.0            # the connection, in seconds
        chunk_bytes: 65536              # the size of each chunk sent
    max_bytes: 26214400                 # the largest file scanned
    on_error: reject                    # or accept_and_flag
    quarantine:
        enabled: true
        directory: '%kernel.project_dir%/var/quarantine'
        tighten_open_directory: true    # false refuses a directory open to other users instead of narrowing it
        service: null                   # the id of a Quarantine service, instead of the directory
        retention_days: 30              # how long phil:upload-scan:purge-quarantine keeps a file
    rescan:
        transport: null                 # the Messenger transport RescanFile is routed to, such as async
    admin:
        enabled: true                   # false leaves the quarantine's page, and its CSRF check, out
        role: ROLE_ADMIN                # the role, or a voter's attribute, that opens the quarantine's page
        layout: '@PhilUploadScan/admin/layout.html.twig'
        page_size: 50                   # 1 to 500 files a page
    integrations:
        media: auto                     # auto, on or off
        monitoring: auto
```

## `scanner`

| Valeur | Analyseur |
|---|---|
| `clamd` | `ClamdScanner`, avec les clés `clamd`. |
| `fake` | `FakeScanner` : la chaîne de test EICAR est infectée, tout le reste est sain ; pour l'environnement de test. |
| `null` | `NullScanner` : chaque fichier est sain. Seulement là où l'analyse est désactivée en connaissance de cause. |
| toute autre chaîne | L'identifiant d'un service qui implémente `Phil\UploadScan\Contract\Scanner`. |

L'interface `Scanner` est un alias du service choisi.

## `clamd`

L'adresse est vérifiée à la construction du conteneur : `unix://<path>` ou `tcp://<host>:<port>`. La documentation de
`phil/upload-scan`, dans son `doc/clamd.md`, décrit le protocole et ce que borne chaque délai d'attente.

## `max_bytes`

Le plus gros fichier envoyé à l'analyseur, 25 Mio par défaut. Un fichier plus gros ne peut pas être analysé : il est
refusé, ou accepté et signalé, selon `on_error`. Gardez cette valeur inférieure ou égale au `StreamMaxLength` de
clamd, et supérieure ou égale au plus gros téléversement que l'application accepte.

## `on_error`

Ce qu'il advient d'un fichier que l'analyseur n'a pas pu analyser — clamd arrêté, trop lent, le fichier trop gros :

- `reject`, la valeur par défaut : le fichier est refusé. Un attaquant qui ralentit l'antivirus ne peut pas faire
  passer un fichier en douce.
- `accept_and_flag` : le fichier est conservé, `FileFlagged` est réparti et `Inspection::flagged()` est vrai.
  L'application le garde hors de vue jusqu'à ce qu'une [nouvelle analyse](rescans.md) le blanchisse.

Un fichier infecté est refusé dans les deux cas.

## `quarantine`

Les fichiers infectés sont copiés dans `directory`, créé lisible par son propriétaire seulement. Un répertoire qui existe déjà et qui est
ouvert à son groupe ou à d'autres utilisateurs est restreint à `0700` avant qu'un fichier y soit conservé ; avec
`tighten_open_directory: false`, ou quand il ne peut pas l'être, le fichier est refusé et non conservé. Un répertoire
sous le `public/` du projet est refusé à la construction du conteneur : le serveur Web servirait ce qu'il contient. `service`
nomme plutôt une `Quarantine` de l'application. `enabled: false`, ou `quarantine: false`, refuse les fichiers infectés
sans les conserver. `retention_days`, 30 par défaut et au moins 1, est la durée pendant laquelle la commande de purge
conserve un fichier. Voir [Quarantaine et événements](quarantine-and-events.md).

## `rescan`

`transport` nomme le transport de Messenger vers lequel `RescanFile` est acheminé : le bundle ajoute la route à la
configuration de FrameworkBundle, avant celle de l'application, si bien qu'une route que l'application donne au message
l'emporte. Le nom compte de 1 à 64 lettres, chiffres, `_`, `.` ou `-` ; `null`, la valeur par défaut, laisse
l'acheminement à l'application, et un message non acheminé est traité au moment où il est réparti. Voir
[Nouvelles analyses](rescans.md).

## `admin`

La page d'administration de la quarantaine, branchée avec Twig et `symfony/security-csrf` une fois ses routes importées :
`role` l'ouvre, `layout` est le gabarit qu'elle étend, et `page_size` le nombre de fichiers listés par page. Voir
[Quarantaine et événements](quarantine-and-events.md#la-page-dadministration).

Ses suppressions exigent le jeton CSRF sans état `phil_upload_scan_quarantine`, par défaut et sans rien à activer :
`csrf_protection` de FrameworkBundle doit alors rester activé, sinon le conteneur ne se construit pas. `enabled: false`
laisse de côté la page, son contrôleur et sa vérification, pour une application qui a installé le composant CSRF pour
ses propres formulaires et désactive la protection. Voir [CSRF](csrf.md).

## `integrations`

`auto` active une intégration quand son bundle est enregistré. `on` exige le bundle, et fait échouer la construction
sans lui. `off` la laisse de côté. Voir [Intégrations](integrations.md). Le contrôle de santé n'est branché que pour
les analyseurs `clamd` et `fake`.
