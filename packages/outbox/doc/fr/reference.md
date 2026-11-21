<!-- phil-dev source-sha256=d6c73322f1a81d7696938a9f31abc7e9b412278c79010522ca990a3e8c31a3b8 -->
# Référence

## Enregistrement

| Type | Rôle |
|---|---|
| `Contract\Outbox` | Le port par lequel une bibliothèque enregistre : `record(string $type, array $payload = [], ?string $orderingKey = null, ?string $idempotencyKey = null): RecordId` |
| `StoreOutbox` | L'outbox sur un entrepôt : vérifie la liste d'autorisation et les bornes, date l'enregistrement, l'ajoute |
| `Payload` | Les identifiants d'un enregistrement par nom, bornés ; `of()`, qui refuse une chaîne contenant `@` ou une espace, `withText()`, `fromJson()`, `toJson()`, `get()`, `has()`, `string()`, `int()` ; `TEXT_PATTERN` |
| `RecordId` | L'identifiant stable d'un enregistrement, 32 chiffres hexadécimaux minuscules ; `generate()`, `fromString()`, `equals()` |
| `SystemClock` | L'horloge du système, en UTC |

## Enregistrements et entrepôts

| Type | Rôle |
|---|---|
| `OutboxRecord` | Un enregistrement tel qu'un entrepôt le conserve : type, JSON de la charge utile, clés, occurrence, disponibilité, état, tentatives, dernière erreur, règlement ; `pending()` en vérifie un nouveau, `payload()` lit sa charge utile, `error()` écrit la classe et le code d'une exception, et le message de celles du paquet seulement ; `OWN_EXCEPTIONS`, l'espace de noms de celles-ci |
| `RecordState` | `Pending`, `Delivered`, `Skipped` ou `Dead` ; `isSettled()`, vrai pour les enregistrements livrés et écartés |
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
| `Relay` | Relaie un lot : bail, reconstruction, répartition, marque ; `BATCH`, `MAX_BATCH`, `LEASE`, `UNKNOWN_TYPE_GRACE`, `MAX_UNKNOWN_TYPE_GRACE` |
| `RelayReport` | Ce qu'une exécution a fait ; `isEmpty()`, `plus()` |
| `Backoff` | Quand un enregistrement est essayé de nouveau, et après combien de tentatives il est mort |
| `Housekeeping` | Les enregistrements morts, la rétention, les comptes ; `RETENTION` |

## Exceptions

Toute exception implémente `Exception\OutboxException`.

| Exception | Lancée quand |
|---|---|
| `Exception\InvalidRecord` | Un type, une clé ou une charge utile est hors de ses bornes, ou une charge utile relue n'en est pas une |
| `Exception\UnknownEventType` | Aucun codec ne reconstruit un type : `record()` le refuse, et le relais essaie de nouveau un enregistrement de ce type pendant le délai de grâce des types inconnus |
| `Exception\UndeliverableRecord` | Un codec déclare qu'un enregistrement ne peut jamais devenir un événement |
| `Exception\ForgottenRecord` | Un codec déclare que ce qu'un enregistrement nomme a été effacé exprès : l'enregistrement est écarté |
| `Exception\InvalidSetting` | Un réglage du relais, de son recul, de la rétention ou des codecs est hors de ses bornes |
