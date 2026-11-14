<!-- phil-dev source-sha256=3f684aa6e5a18638ba243d2492c8726f861a5a7dd815b0068aff58d9080bc77b -->
# Points d'extension

Le bundle fournit des ports de deux sortes. Sous `Phil\AuditBundle\Domain\Contract`, les deux que l'application écrit
toujours, `CanonicalSerializer` et `AnchorWriter`, avec `AnchorReader` et `AuditRowRepository` à côté. Sous
`Domain\Contract` aussi et sous `Application\Contract`, les ports du côté lecture, de l'alimentation et du contexte,
chacun avec un adaptateur que le bundle enregistre quand il le peut, et que l'application remplace en faisant de ce
port l'alias de son propre service.

## `CanonicalSerializer`

Les octets sur lesquels l'empreinte d'une ligne est calculée. Le contexte et les décisions à figer :
[serialization-door.md](serialization-door.md).

| Méthode | Contrat |
|---|---|
| `version(): non-empty-string` | L'identifiant écrit dans chaque ligne dont l'empreinte est calculée sous ce sérialiseur, couvert par l'empreinte de la ligne, et relu par quiconque décode la charge utile. Stable pendant toute la vie du sérialiseur : le changer crée une nouvelle version. |
| `serialize(array<string, scalar\|null> $fields): string` | Des octets déterministes pour les champs non personnels et les engagements, par nom. Mêmes champs, mêmes octets, toujours. |

Les invariants que respecte un adaptateur :

- **déterministe** : indépendant de l'ordre d'insertion dans le tableau, de la version de PHP, de la locale et des
  indicateurs;
- **injectif** : deux ensembles de champs différents ne produisent jamais les mêmes octets;
- **sûr pour le binaire** : les octets d'engagement (32 octets bruts) sont encodés sans perte;
- **aucun renseignement personnel en clair** : un champ personnel est transmis sous forme de son engagement;
- **figé dès qu'une ligne est ancrée sous lui** : un changement est une nouvelle classe avec une nouvelle `version()`,
  et l'ancienne classe reste pour décoder les lignes écrites sous elle.

Gardez chaque version que l'application a déjà écrite à la disposition de ce qui décode ou revérifie une charge utile,
indexée par `version()` (la vérification propre au bundle calcule l'empreinte de la charge utile telle qu'elle est
stockée et n'en a besoin d'aucune) :

```php
final readonly class SerializerRegistry
{
    /** @var array<string, CanonicalSerializer> */
    private array $byVersion;

    /** @param iterable<CanonicalSerializer> $serializers */
    public function __construct(iterable $serializers, private string $current)
    {
        $byVersion = [];
        foreach ($serializers as $serializer) {
            $version = $serializer->version();
            if (isset($byVersion[$version])) {
                throw new LogicException("Two serializers claim version $version");
            }
            $byVersion[$version] = $serializer;
        }
        $this->byVersion = $byVersion;
    }

    public function forWriting(): CanonicalSerializer { return $this->byVersion[$this->current]; }

    public function forRow(string $version): CanonicalSerializer
    {
        return $this->byVersion[$version] ?? throw new UnexpectedValueException("Unknown serialization version $version");
    }
}
```

Un exemple d'implémentation se trouve dans [serialization-door.md](serialization-door.md).

## `AnchorWriter`

Écrit l'ancre d'une période hors de la base de données. Contexte : [anchoring.md](anchoring.md).

| Méthode | Contrat |
|---|---|
| `write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void` | Persiste les empreintes de bornes d'une période d'un segment dans un stockage en ajout seul. Lève une exception si l'écriture n'a pas réussi. |

Les invariants que respecte un adaptateur :

- **destination en ajout seul** : verrouillage d'objets en mode conformité, WORM, ou au moins un identifiant de
  connexion avec l'accès en écriture et sans suppression ni écrasement;
- **un identifiant de connexion qui lui est propre**, distinct de celui des sauvegardes et inutilisable pour supprimer
  ou écraser des ancres;
- **aucun échec silencieux** : une exception sur toute erreur, pour que l'appelant puisse alerter;
- **aucun écrasement** : une clé d'objet qui inclut le segment et la fin de période, pour qu'une deuxième écriture pour
  la même période soit un nouvel objet (ou soit refusée), jamais un remplacement;
- **sans perte** : les empreintes stockées exactement telles que reçues; la fin de période stockée avec son fuseau
  horaire ou en UTC.

```php
use Aws\S3\S3Client;
use Phil\AuditBundle\Domain\Contract\AnchorWriter;

final readonly class ObjectLockAnchorWriter implements AnchorWriter
{
    public function __construct(private S3Client $s3, private string $bucket) {}

    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
    {
        $end = $periodEnd->setTimezone(new DateTimeZone('UTC'));

        $this->s3->putObject([
            'Bucket' => $this->bucket,                       // bucket with object lock, compliance mode by default
            'Key' => sprintf('anchors/%s/%s.json', $segment, $end->format('Y-m-d\THis\Z')),
            'Body' => json_encode([
                'segment' => $segment,
                'period_end' => $end->format(DATE_ATOM),
                'first_hash' => bin2hex($firstHash),
                'head_hash' => bin2hex($headHash),
            ], JSON_THROW_ON_ERROR),
        ]);                                                  // the SDK throws on failure; under object lock,
                                                             // a second write is a new version, never a replacement
    }
}
```

(Dans cet exemple, `aws/aws-sdk-php` est une dépendance de l'application, pas du bundle. L'exemple suppose des
empreintes binaires brutes; retirez `bin2hex()` si votre chaîne utilise déjà l'hexadécimal.)

## `AuditRowRepository`

`DbalAuditRowRepository` l'implémente sur une table; une application qui garde la piste ailleurs l'implémente et
définit `connection: null`.

| Méthode | Contrat |
|---|---|
| `first(string $segment): ?AuditRow`, `head(string $segment): ?AuditRow` | La séquence la plus basse et la plus haute du segment, lues sur la clé |
| `append(AuditRow $row): void` | Insère la ligne; une place déjà prise lève `ChainConflict`, sans jamais écraser |
| `rows(string $segment): iterable` | Chaque ligne du segment, par séquence, en flux |
| `rowsAfter(string $segment, int $sequence, int $limit): array` | Au plus `$limit` lignes dont la séquence dépasse `$sequence`, par séquence, lues sur la clé : ce que lit l'alimentation |
| `segments(): array` | Chaque segment qui a une ligne |

## Les ports de l'alimentation

| Port | Méthodes | Adaptateurs dans le bundle |
|---|---|---|
| `Domain\Contract\FactExporter` | `export(non-empty-list<ExportedFact> $facts): void`, dans l'ordre; lève une exception quand le lot n'est pas arrivé | `JsonLinesFactExporter`, `OtlpLogExporter` ([dashboards.md](dashboards.md)) |
| `Domain\Contract\FeedCheckpoints` | `position(string $feed, string $segment): int`, 0 pour aucun; `advance(string $feed, string $segment, int $sequence): void`, jamais en arrière | `DbalFeedCheckpoints` |

Un exportateur ne reçoit que des identifiants, et on peut l'appeler sans risque deux fois avec le même lot :
l'alimentation renvoie un lot après un échec. Un magasin d'emplacements garde une place par alimentation et par segment,
et ignore une séquence qui lui est égale ou inférieure.

## Les ports du côté lecture et du contexte

| Port | Méthodes | Adaptateurs du bundle |
|---|---|---|
| `Domain\Contract\AuditLogReader` | `page(AuditQuery): AuditPage`, `row(string $segment, int $sequence): ?AuditRow` | `DbalAuditLogReader`, `ScanningAuditLogReader` ([reading.md](reading.md)) |
| `Application\Contract\TenantScope` | `current(): ?string`, le locataire dans lequel agit l'unité de travail | `TenancyTenantScope` |
| `Application\Contract\ActorResolver` | `current(): ?string`, l'identifiant stable de qui agit | `SecurityActorResolver` |
| `Application\Contract\AuditNotifier` | `recorded(AuditRow): void`, une fois qu'une ligne est dans la piste | `MessengerAuditNotifier` |
| `Application\Contract\PayloadReader` | `fields(AuditRow): ?array`, les champs d'une charge utile que lit une personne, `null` pour une version qu'il ne lit pas | aucun : l'inverse du sérialiseur de l'application relève de l'application |

Deux interfaces sont implémentées par les propres classes de l'application :

| Interface | Implémentée par | Méthode |
|---|---|---|
| `Application\Contract\AuditActor` | la classe de l'utilisateur | `auditActorId(): string`, son identifiant stable |
| `Application\Contract\Auditable` | le sujet d'un workflow | `auditSubject(): Subject` |

Les invariants que respecte un adaptateur :

- **des identifiants seulement** : un acteur, un locataire ou une notification ne porte jamais un nom ni une adresse;
- **en lecture seule** : un `AuditLogReader` ne change rien, et donne les lignes dans l'ordre de la requête, après son
  curseur, au plus sa limite, avec `next` défini seulement s'il reste une ligne;
- **l'emplacement, pas le contenu** : un `AuditNotifier` envoie où se trouve la ligne, et un consommateur la relit.

Un `PayloadReader` pour le sérialiseur à préfixe de longueur de [serialization-door.md](serialization-door.md) indexe
ses décodeurs par version :

```php
final readonly class PayloadReaders implements PayloadReader
{
    /** @param array<string, Closure(string): array<string, scalar|null>> $decoders by version */
    public function __construct(private array $decoders) {}

    public function fields(AuditRow $row): ?array
    {
        return isset($this->decoders[$row->version]) ? ($this->decoders[$row->version])($row->payload) : null;
    }
}
```

## Les objets-valeurs sur lesquels vous construisez

`Salt` et `Commitment` sont finales et ne sont pas faites pour être étendues; composez-les dans votre modèle de ligne
([commitments-and-erasure.md](commitments-and-erasure.md)).

## Les services sur lesquels compter

`AuditTrail`, `AuditLog`, `ChainStatus`, `TrailVerification`, `Anchoring` et `DashboardFeed` sont publics, avec les
alias des ports que [configuration.md](configuration.md) liste. Enregistrez vous-même les adaptateurs des deux ports
que l'application écrit toujours.
