<!-- phil-dev source-sha256=3e72e23fbbf8505cd24d3eeb04c9edcc477a138903ed0e3bc9fbf0fd173ffa06 -->
# Référence

## Enregistrement

| Type | Rôle |
|---|---|
| `Contract\Outbox` | Le port par lequel une bibliothèque enregistre : `record(string $type, array $payload = [], ?string $orderingKey = null, ?string $idempotencyKey = null): RecordId` |
| `StoreOutbox` | L'outbox sur un entrepôt : vérifie la liste d'autorisation et les bornes, date l'enregistrement, l'ajoute |
| `Payload` | Les identifiants d'un enregistrement par nom, bornés ; `of()`, `fromJson()`, `toJson()`, `get()`, `has()`, `string()`, `int()` |
| `RecordId` | L'identifiant stable d'un enregistrement, 32 chiffres hexadécimaux minuscules ; `generate()`, `fromString()`, `equals()` |
| `SystemClock` | L'horloge du système, en UTC |

## Enregistrements et entrepôts

| Type | Rôle |
|---|---|
| `OutboxRecord` | Un enregistrement tel qu'un entrepôt le conserve : type, JSON de la charge utile, clés, occurrence, disponibilité, état, tentatives, dernière erreur, règlement ; `pending()` en vérifie un nouveau, `payload()` lit sa charge utile, `error()` écrit l'erreur d'une exception |
| `RecordState` | `Pending`, `Delivered` ou `Dead` |
| `Contract\OutboxStore` | Où les enregistrements sont conservés |
| `Memory\InMemoryOutboxStore` | Les enregistrements dans la mémoire d'un seul processus |
| `Test\OutboxStoreTestCase` | Les tests de contrat que tout entrepôt réussit |

## Codecs

| Type | Rôle |
|---|---|
| `Contract\EventCodec` | Reconstruit les événements de certains types |
| `Codecs` | Les codecs par type : la liste d'autorisation |
| `RecordedEventCodec` | Reconstruit les enregistrements des types qu'on lui donne sous forme de `RecordedEvent` |
| `RecordedEvent` | L'enregistrement tel quel : identifiant, type, charge utile, clé d'ordre, occurrence, tentative |

## Relais et entretien

| Type | Rôle |
|---|---|
| `Relay` | Relaie un lot : bail, reconstruction, répartition, marque ; `BATCH`, `MAX_BATCH`, `LEASE` |
| `RelayReport` | Ce qu'une exécution a fait ; `isEmpty()`, `plus()` |
| `Backoff` | Quand un enregistrement est essayé de nouveau, et après combien de tentatives il est mort |
| `Housekeeping` | Les enregistrements morts, la rétention, les comptes ; `RETENTION` |

## Exceptions

Toute exception implémente `Exception\OutboxException`.

| Exception | Lancée quand |
|---|---|
| `Exception\InvalidRecord` | Un type, une clé ou une charge utile est hors de ses bornes, ou une charge utile relue n'en est pas une |
| `Exception\UnknownEventType` | Aucun codec ne reconstruit un type |
| `Exception\UndeliverableRecord` | Un codec déclare qu'un enregistrement ne peut jamais devenir un événement |
| `Exception\InvalidSetting` | Un réglage du relais, de son recul, de la rétention ou des codecs est hors de ses bornes |
