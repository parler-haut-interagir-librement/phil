<!-- phil-dev source-sha256=eb32b0a1e1080a4d6d98421a5388daee8f028187407067a286201a465c9e5213 -->
# Alimenter les tableaux de bord

La piste est le registre; un tableau de bord en est une vue. L'alimentation envoie les faits de chaque ligne — où elle
se trouve, quand, de quel genre, l'action, l'acteur, l'enregistrement, le locataire et l'empreinte — vers une
destination que lit un tableau de bord : un fichier qu'un expéditeur de journaux suit, un collecteur OpenTelemetry, ou
un exportateur de votre cru. **Elle n'envoie que des identifiants** : jamais la charge utile, de sorte qu'aucun champ de
la piste, personnel ou non, ne la quitte. Une personne qui a besoin des champs lit la ligne là où elle est, par
`AuditLog::row()`.

## L'activer

Un exportateur par alimentation :

```yaml
phil_audit:
    feed:
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl'     # or php://stdout in a container
```

```yaml
phil_audit:
    feed:
        name: siem                                            # the key of its places; default "dashboards"
        batch: 500                                            # rows read and sent at once, 1 to 500
        otlp:
            endpoint: 'https://collector.internal:4318/v1/logs'
            service_name: shop
            headers: { Authorization: '%env(OTEL_COLLECTOR_AUTHORIZATION)%' }
            http_client: http_client
```

```yaml
phil_audit:
    feed:
        exporter: App\Audit\WarehouseExporter                 # a FactExporter of yours
        checkpoints: App\Audit\RedisFeedPlaces                # a FeedCheckpoints of yours; default, a table
```

Définir deux de `exporter`, `json_lines` et `otlp.endpoint` fait échouer la construction du conteneur. Sans aucun,
aucune alimentation n'est enregistrée.

## Comment elle s'exécute

`DashboardFeed` garde un emplacement par segment : la séquence de la dernière ligne qu'il a envoyée. Une exécution lit
les lignes après cet emplacement sur la clé `(segment, sequence)`, un lot à la fois, envoie chaque lot par
l'exportateur, et déplace l'emplacement au-delà une fois que l'exportateur a répondu. La séquence d'un segment n'a
aucun trou et ne change jamais : une ligne consignée en retard, ou avec un instant antérieur à celui des lignes qui
l'entourent, n'est donc jamais sautée — ce qu'une alimentation fondée sur les instants ne pourrait pas promettre.

- **Au moins une fois.** Un lot dont l'envoi lève une exception (`ExportFailed`) laisse l'emplacement où il était :
  l'exécution suivante l'envoie de nouveau. Un récepteur qui garde le couple `segment` et `sequence` écarte une ligne
  qu'il a déjà.
- **Bornée.** `feed(?array $segments = null, int $max = PHP_INT_MAX)` envoie au plus `$max` lignes; l'exécution suivante
  reprend là où elle s'est arrêtée. `lag($segment)` dit combien de lignes d'un segment attendent, lu sur la clé.
- **Nommée.** Deux alimentations vers deux destinations gardent deux jeux d'emplacements, selon leur `name`.

Exécutez-la depuis un planificateur :

```bash
bin/console phil:audit:feed --max=100000         # every segment
bin/console phil:audit:feed orders members       # those segments only
```

Ou après chaque ligne : avec `dispatch_bus` défini, le bundle enregistre un *handler*, `FeedOnRecorded`, qui alimente
le segment de chaque message `AuditRecorded`. Acheminez le message vers un transport asynchrone, et les tableaux de
bord suivent la piste en quelques secondes sans ralentir la requête qui a consigné la ligne. Un message traité deux
fois, ou en retard, n'envoie rien de ce que l'emplacement a déjà dépassé.

```yaml
phil_audit:
    dispatch_bus: messenger.bus.default
    feed:
        otlp: { endpoint: 'https://collector.internal:4318/v1/logs', service_name: shop }

framework:
    messenger:
        routing:
            Phil\AuditBundle\Application\Message\AuditRecorded: async
```

Deux exécutions de la même alimentation en même temps peuvent envoyer un lot deux fois; la déduplication du récepteur
y pourvoit. Pour éviter le double envoi, gardez un seul consommateur sur le transport, ou exécutez la commande sous un
verrou.

## Ce qui est envoyé

`ExportedFact::toArray()`, chaque clé présente, `null` pour un fait que la ligne n'a pas :

| Clé | Valeur |
|---|---|
| `segment`, `sequence` | L'emplacement de la ligne : son identité |
| `recorded_at` | L'instant, en UTC, à la microseconde, tel que la piste le garde |
| `kind` | `event` ou `discontinuity` |
| `action`, `actor`, `subject_type`, `subject_id`, `tenant` | Les faits, des identifiants seulement |
| `hash` | L'empreinte de la ligne, pour un récepteur qui vérifie une ligne par rapport à la piste |

### JSON Lines

`JsonLinesFactExporter` ajoute un objet JSON par ligne, chaque lot en une seule écriture sous un verrou exclusif, avec
les barres obliques et les caractères non ASCII non échappés :

```json
{"segment":"orders","sequence":1,"recorded_at":"2026-11-12T09:00:00.000000Z","kind":"event","action":"order.paid","actor":"acct-7","subject_type":"order","subject_id":"42","tenant":"shop-a","hash":"…"}
```

Un flux `php://`, comme `php://stdout`, est écrit sans le verrou, que seul un fichier prend. Une écriture qui échoue
lève `ExportFailed`.

### Journaux OpenTelemetry

`OtlpLogExporter` envoie chaque lot par POST au point de terminaison des journaux du collecteur, en OTLP sur HTTP dans
son encodage JSON, avec un délai de 10 secondes : une ressource dont le `service.name` est `service_name`, une portée,
`phil/audit-bundle`, et un enregistrement de journal par fait. Le `timeUnixNano` d'un enregistrement est l'instant de la
ligne, sa gravité `INFO` (9), son corps l'action — le genre de la ligne quand elle n'en a pas —, et ses attributs les
faits sous `phil.audit.*` : `phil.audit.segment`, `phil.audit.sequence` (un entier), `phil.audit.kind`,
`phil.audit.action`, `phil.audit.actor`, `phil.audit.subject_type`, `phil.audit.subject_id`, `phil.audit.tenant` et
`phil.audit.hash`, chacun présent seulement quand la ligne l'a. Une réponse autre que 2xx, ou aucune, lève
`ExportFailed`. Les redirections ne sont jamais suivies (`max_redirects: 0`) : un collecteur qui répond `3xx` fait
échouer le lot, de sorte que les faits et les en-têtes, une autorisation comprise, n'atteignent jamais un autre hôte.
L'exportateur a besoin de `symfony/http-client` et de `phil/url`.

Le point de terminaison est lu par `phil/url` pendant la compilation du conteneur, et un point refusé l'arrête :
`http` ou `https`, un nom d'hôte d'au moins deux étiquettes, comme `collector.internal`, ou `localhost` pour un
collecteur à côté de l'application. Une adresse avec un nom d'utilisateur ou un mot de passe, une adresse IP, ou un
nom simple, comme celui d'un service de conteneur, est refusée; donnez au collecteur un nom de deux étiquettes, par le
DNS du réseau ou un alias.

### Un exportateur de votre cru

```php
use Phil\AuditBundle\Domain\Contract\FactExporter;

final readonly class WarehouseExporter implements FactExporter
{
    public function __construct(private Connection $warehouse) {}

    public function export(array $facts): void
    {
        $this->warehouse->transactional(function () use ($facts): void {
            foreach ($facts as $fact) {
                // INSERT … ON CONFLICT (segment, sequence) DO NOTHING: a batch sent again changes nothing.
                $this->warehouse->executeStatement('INSERT INTO audit_facts …', $fact->toArray());
            }
        });
    }
}
```

Levez une exception quand le lot n'est pas arrivé : l'alimentation garde son emplacement.

## Les emplacements

Par défaut, les emplacements vivent dans `phil_audit_feed` sur la connexion de la piste — une ligne par alimentation et
par segment, sa clé primaire `(feed, segment)` —, que `AuditSchemaListener` ajoute au schéma avec la table de la piste.
Un emplacement ne va que vers l'avant : `DbalFeedCheckpoints::advance()` n'écrit jamais une séquence inférieure à celle
qui est stockée. Le rôle de base de données de l'alimentation doit pouvoir insérer, sélectionner et mettre à jour cette
table, et ne rien faire d'autre que sélectionner sur la table de la piste.
