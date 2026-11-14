<!-- phil-dev source-sha256=601b64205cea775d59d2cbf34b88ab00fa18662802538723d57978461ccda064 -->
# Configuration

```yaml
# config/packages/phil_audit.yaml
phil_audit:
    connection: default                      # the Doctrine connection of the table; null: your own repository
    serializer: App\Audit\CanonicalSerializerV1   # your frozen CanonicalSerializer
    anchor_writer: App\Audit\ObjectLockAnchors    # to append-only storage
    anchor_reader: App\Audit\ObjectLockAnchors    # reads them back for verification
    # anchor_file: '%kernel.project_dir%/var/audit-anchors.jsonl'   # development only
    dispatch_bus: messenger.bus.default           # each recorded row dispatched as an AuditRecorded message
    workflow_segment: states                      # each transition of an Auditable subject recorded there
    site_segments: true                           # each tenant's rows in a chain of its own
    feed:                                         # the facts sent to dashboards, identifiers only
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl'
```

| Clé | Valeur par défaut | Effet |
|---|---|---|
| `connection` | `default`; `null` sans DoctrineBundle | La connexion de `phil_audit_row`, par `DbalAuditRowRepository`. Avec `null`, le bundle n'enregistre rien : l'application fournit son propre `AuditRowRepository` et câble les services par-dessus |
| `serializer` | `null` | Le service du `CanonicalSerializer` avec lequel les lignes sont écrites; sans lui, aucun `AuditTrail` |
| `anchor_writer` | `null` | L'`AnchorWriter`; sans lui (et sans `anchor_file`), ni `Anchoring` ni `phil:audit:anchor` |
| `anchor_reader` | `null` | L'`AnchorReader` avec lequel la vérification relit les ancres; sans lui, la chaîne est vérifiée sans ancres |
| `anchor_file` | `null` | Un `JsonLinesAnchorStore` sur ce fichier, comme auteur et lecteur quand ils ne sont pas fournis : en ajout seul uniquement sur un stockage WORM, donc pour le développement et les tests |
| `dispatch_bus` | `null` | L'identifiant de service d'un bus de Messenger sur lequel chaque ligne consignée est envoyée, sous la forme d'un message `AuditRecorded` qui ne porte que son emplacement ([integrations.md](integrations.md)) |
| `workflow_segment` | `null` | Le segment où sont consignées les transitions terminées des sujets `Auditable` d'un workflow; il exige `serializer`, et la construction du conteneur échoue sans lui |
| `site_segments` | `false` | Si les lignes de chaque locataire vont dans un segment qui lui est propre, chaîné, ancré et vérifié séparément ([sites.md](sites.md)) |
| `feed.name` | `dashboards` | Le nom de l'alimentation, la clé de ses emplacements |
| `feed.batch` | `500` | Le nombre de lignes lues et envoyées à la fois, de 1 à 500 |
| `feed.exporter` | `null` | L'identifiant de service d'un `FactExporter` de l'application |
| `feed.json_lines` | `null` | Un fichier, ou un flux `php://`, auquel les faits sont ajoutés en JSON Lines |
| `feed.otlp.endpoint` | `null` | Le point de terminaison des journaux d'un collecteur OpenTelemetry auquel les faits sont envoyés par POST, lu par `phil/url` : un nom d'hôte d'au moins deux étiquettes, ou `localhost`; une adresse refusée arrête la compilation ([dashboards.md](dashboards.md)) |
| `feed.otlp.service_name` | `symfony` | Le `service.name` de la ressource |
| `feed.otlp.headers` | `{}` | Les en-têtes envoyés avec chaque requête |
| `feed.otlp.http_client` | `http_client` | L'identifiant de service du client HTTP |
| `feed.checkpoints` | `null` | L'identifiant de service d'un `FeedCheckpoints`; sans lui, la table `phil_audit_feed` de la connexion de la piste |

Au plus un de `feed.exporter`, `feed.json_lines` et `feed.otlp.endpoint` est défini; deux font échouer la construction
du conteneur, et aucun n'enregistre d'alimentation ([dashboards.md](dashboards.md)).

Une clé inconnue est refusée à la construction du conteneur.

## Services câblés

Avec une connexion :

| Identifiant de service | Public | Définition |
|---|---|---|
| `DbalAuditRowRepository`, alias `AuditRowRepository` | l'alias | Sur `doctrine.dbal.<connection>_connection` |
| `AuditSchemaListener` | non | `postGenerateSchema` sur cette connexion : les migrations créent `phil_audit_row` et `phil_audit_feed`, le locataire, l'acteur et l'identifiant de l'enregistrement dans la collation binaire de la plateforme de la connexion ([reading.md](reading.md#comparaison-exacte)) |
| `AuditTrail` | oui | Avec `serializer`, l'horloge, le `TenantScope` et l'`AuditNotifier` quand il y en a, et `site_segments` |
| `DbalAuditLogReader`, alias `AuditLogReader` | non | Sur la même connexion |
| `AuditLog` | oui | Avec l'`AuditLogReader` et le `TenantScope` quand il y en a un ([reading.md](reading.md)) |
| `ChainStatus` | oui | Avec le dépôt, `TrailVerification` et le `TenantScope` quand il y en a un |
| `MessengerAuditNotifier`, alias `AuditNotifier` | non | Avec `dispatch_bus` |
| `TenancyTenantScope`, alias `TenantScope` | non | Quand `phil/tenancy-bundle` est enregistré |
| `SecurityActorResolver`, alias `ActorResolver` | non | Quand SecurityBundle est enregistré |
| `WorkflowAuditListener` | non | Avec `workflow_segment`, sur `workflow.completed` |
| `TrailVerification` | oui | Avec le lecteur d'ancres, s'il y en a un |
| `Anchoring` | oui | Avec l'auteur d'ancres |
| `JsonLinesAnchorStore` | non | Avec `anchor_file` |
| `phil:audit:verify`, `phil:audit:anchor` | — | Avec Symfony Console; la seconde avec un auteur d'ancres |
| `JsonLinesFactExporter`, `OtlpLogExporter` | non | Avec `feed.json_lines`, avec `feed.otlp.endpoint` |
| `DbalFeedCheckpoints` | non | Avec une alimentation et sans `feed.checkpoints` |
| `DashboardFeed` | oui | Avec une alimentation : le dépôt, l'exportateur, les emplacements, `feed.name` et `feed.batch` |
| `phil:audit:feed` | — | Avec une alimentation et Symfony Console |
| `FeedOnRecorded` | non | Avec une alimentation et `dispatch_bus` : un *handler* de `AuditRecorded` |

Ne faites pas de `CanonicalSerializer` l'alias d'une seule implémentation si vous prévoyez plus d'une version : chaque
ligne consigne la version sous laquelle elle a été sérialisée ([serialization-door.md](serialization-door.md)).

Le partitionnement de la table, les rôles de base de données qui peuvent insérer et sélectionner (et jamais modifier ni
supprimer), les durées de conservation, les événements audités et les champs déclarés personnels relèvent tous de
l'application.
