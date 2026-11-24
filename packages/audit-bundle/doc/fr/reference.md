<!-- phil-dev source-sha256=214187f8f621e49361fc2809c1e894a51be1cb0fbcb0b93f02705171b63abeec -->
# Référence de l'API

Chaque type sous `Phil\AuditBundle\`, par couche.

## Bundle

| Type | Rôle | État |
|---|---|---|
| `PhilAuditBundle` | `AbstractBundle`, alias `phil_audit` | Configuré et câblé ([configuration.md](configuration.md)) |

## Domain

### `Domain\Commitment`

| Type | Rôle | État |
|---|---|---|
| `Salt` | Clé HMAC propre à une ligne et à un champ; `MIN_BYTES = 16`; `generate()`, `fromBytes()`; `$bytes` public | Implémenté et testé |
| `Commitment` | `HMAC-SHA256(key = salt, message = value)`, 32 octets bruts; `of()`, `fromBytes()`, `matches()`, `equals()` en temps constant; `$bytes` public | Implémenté et testé |

### `Domain\Chain`

| Type | Rôle | État |
|---|---|---|
| `AuditRow`, `RowKind` | Une ligne : segment, séquence, genre, instant, version de sérialisation, charge utile, empreinte précédente, empreinte; `append()`, `restore()`, `isIntact()`; `GENESIS` | Implémenté et testé |
| `Anchor` | Les empreintes de bornes d'un segment à la fin d'une période | Implémenté et testé |
| `ChainVerifier`, `ChainBreak` | Vérifie un segment par rapport à ses ancres en une seule passe; ce qui ne va pas, une alarme ou une rupture expliquée par une discontinuité ancrée (`isAlarm()`) | Implémenté et testé |
| `Facts`, `Subject` | Ce qui indexe une ligne et que son empreinte couvre : action, acteur, sujet, locataire; `of()`, `read()`, `none()`, `inTenant()`, `restore()`, `isWellFormed()`, `isRead()` | Implémenté et testé |
| `SiteSegment` | Le segment d'un locataire : `of()`, `isSite()`, `belongsTo()`, `base()`; `DIGEST_LENGTH = 32` (128 bits), `MAX_BASE_LENGTH = 29` | Implémenté et testé |

### `Domain\Contract`

| Type | Rôle | État |
|---|---|---|
| `CanonicalSerializer` | `version()` et `serialize($fields)` : les octets figés sur lesquels l'empreinte d'une ligne est calculée | Port seulement; aucune implémentation fournie, à dessein |
| `AnchorWriter` | `write($segment, $periodEnd, $firstHash, $headHash)` vers un stockage en ajout seul | Port |
| `AnchorReader` | `anchors($segment)` : les ancres écrites, de la plus ancienne à la plus récente | Port |
| `AuditRowRepository` | L'endroit où les lignes sont ajoutées, lues dans l'ordre (`rows()`, `rowsAfter()`), et où les deux extrémités d'un segment (`first()`, `head()`) sont lues par la clé | Port |
| `AuditLogReader` | `page()`, `row()` | Port |
| `FactExporter` | `export($facts)` : un lot de faits vers un tableau de bord | Port |
| `FeedCheckpoints` | `position($feed, $segment)`, `advance($feed, $segment, $sequence)` : là où une alimentation s'est arrêtée | Port |

### `Domain\Exception` et `Domain\Export`

| Type | Rôle | État |
|---|---|---|
| `ChainConflict` | Une place d'un segment prise deux fois | Exception |
| `ExportedFact` | Ce qu'un tableau de bord reçoit d'une ligne, des identifiants seulement; `of()`, `toArray()`, `$unixNanos` | Implémenté et testé |

### `Domain\Read`

| Type | Rôle | État |
|---|---|---|
| `AuditQuery`, `Order` | Les filtres, l'ordre, la taille de page et le curseur d'une lecture, `reads()` pour les lectures seulement; `matches()` et `isAfterCursor()` pour un adaptateur qui balaie | Implémenté et testé |
| `Cursor`, `AuditPage` | Où une page s'est arrêtée, sous forme de jeton; une page et le curseur de la suivante | Implémenté et testé |
| `SegmentStatus`, `ChainState` | La tête d'un segment et, une fois vérifié, ses ruptures et son état; son site (`site()`) et le segment nommé (`base()`) | Implémenté et testé |

## Application

| Type | Rôle | État |
|---|---|---|
| `AuditTrail` | `record($segment, $fields, $facts)`, `recordRead($segment, $what, $subject, $actor, $fields)`, `recordDiscontinuity($segment, $reason, $tenant)`, `segmentFor($segment, $tenant)`, retenté en cas de concurrence, estampillé du locataire, dans le segment du site avec les segments de site, passé au notificateur | Implémenté et testé |
| `AuditLog` | `page()`, `history()`, `row()`, `each()`, limité au locataire courant, chaque ligne soumise à la requête avec `===` | Implémenté et testé |
| `ChainStatus` | `segments()`, `verify($segment)`, limité au site du locataire courant | Implémenté et testé |
| `DashboardFeed` | `feed($segments, $max)`, `feedSegment($segment, $max)`, `lag($segment)` : les faits depuis l'emplacement de l'alimentation | Implémenté et testé |
| `Exception\ExportFailed` | Un lot qui n'est pas arrivé à destination | Exception |
| `Contract\TenantScope`, `Contract\ActorResolver`, `Contract\AuditNotifier`, `Contract\PayloadReader` | Les ports du contexte et de la lecture | Ports |
| `Contract\AuditActor`, `Contract\Auditable` | Implémentées par la classe de l'utilisateur et par le sujet d'un workflow | Interfaces |
| `Message\AuditRecorded` | L'emplacement d'une ligne consignée, envoyé sur un bus | Implémenté et testé |
| `Anchoring` | Ancre chaque segment à partir de ses lignes de début et de tête, sans lire les lignes entre les deux | Implémenté et testé |
| `TrailVerification` | Vérifie chaque segment, ou ceux qui sont nommés, ou ceux d'un site | Implémenté et testé |

## Infrastructure

| Type | Rôle | État |
|---|---|---|
| `DbalAuditRowRepository`, `AuditTable`, `AuditSchemaListener` | Les tables `phil_audit_row` et `phil_audit_feed` d'une connexion Doctrine, pour sa plateforme (`AuditTable::configure($schema, $platform)`); le locataire, l'acteur et l'identifiant de l'enregistrement dans une collation binaire (`AuditTable::binaryCollation()`) | Implémenté et testé |
| `DbalFeedCheckpoints` | Les emplacements des alimentations dans `phil_audit_feed`, toujours déplacés vers l'avant seulement | Implémenté et testé |
| `JsonLinesFactExporter`, `OtlpLogExporter` | Des faits en JSON Lines; des faits en enregistrements de journal OpenTelemetry sur OTLP/HTTP | Implémenté et testé |
| `JsonLinesAnchorStore` | Des ancres ajoutées à un fichier : développement et tests | Implémenté et testé |
| `AnchorCommand`, `VerifyCommand`, `FeedCommand` | `phil:audit:anchor`, `phil:audit:verify [--tenant] [--accept-explained]`, `phil:audit:feed [--max]` | Implémenté et testé |
| `DbalAuditLogReader`, `ScanningAuditLogReader` | Le journal lu sur les index de la table, chaque ligne soumise à la requête avec `===`, ou en balayant un dépôt | Implémenté et testé |
| `MessengerAuditNotifier`, `TenancyTenantScope`, `SecurityActorResolver`, `WorkflowAuditListener` | Les adaptateurs du contexte | Implémenté et testé |
| `FeedOnRecorded` | Alimente le segment de chaque message `AuditRecorded` | Implémenté et testé |
| `ContextPass` | Retire un adaptateur du contexte dont le service manque | Implémenté et testé |

## Erreurs

`ChainConflict` (un `DomainError`) pour une place dans un segment prise deux fois; `ExportFailed` (une
`RuntimeException`) pour un lot de faits qui n'est pas arrivé. `Salt::fromBytes()`, `Commitment::fromBytes()`,
`AuditRow::append()`, `Facts`, `Subject`, `SiteSegment::of()`, `AuditQuery` et `Cursor::fromToken()` lèvent
`Phil\DddBundle\Domain\Exception\InvalidValue` de `phil/ddd-bundle`.

## Pas commencé

L'opération d'effacement : l'application supprime le texte en clair et le sel d'un champ et garde l'engagement. Voir
[hash-chain.md](hash-chain.md).
