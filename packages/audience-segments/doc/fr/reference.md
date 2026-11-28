<!-- phil-dev source-sha256=f2603976fac6611320feed7555caa683004eddb24629f2e00484f1d70f75c685 -->
# Référence

Chaque type public du paquet, avec son rôle. Les noms sont relatifs à `Phil\AudienceSegments`.

## Segments

| Type | Genre | Rôle |
|---|---|---|
| `Segment` | `final readonly class` | `id`, `name` et `rule` d'un segment ; refuse les types de condition interdits ; `MEMBER`, `FORBIDDEN_WORDS`. |
| `SegmentJson` | `final readonly class` | Le langage de requête : `read(string $id, string $json)`, `fromArray()`, `write()`, `toArray()`, `fingerprint()`. |
| `AudienceConditions` | `final class` | `types(?Participation, ?Purchases, ?Supporters, ?PeopleTags): ConditionTypes`. |
| `Segments` | `final readonly class` | `check(Segment, string $member): Membership`, `page(Segment, ?string $after, int $limit): MemberPage`, `freeze(Segment): FrozenList`, `walk(Segment)`. |
| `Recalculation` | `final readonly class` | `recalculate(Segment): RecalculationReport`. |
| `Membership` | `final readonly class` | `member`, `excluded`, `result` ; `isMember()`. |
| `MemberPage` | `final readonly class` | `members`, `next`. |
| `FrozenList` | `final readonly class` | `segment`, `name`, `fingerprint`, `frozenAt`, `members` ; `count()`, `contains()`, `page()`. |
| `RecalculationReport` | `final readonly class` | `segment`, `members`, `entered`, `left`, `at`. |
| `PurchaseFilter` | `final readonly class` | `skus`, `products`, `orderStates`. |

## Types de condition

| Type | Genre | Rôle |
|---|---|---|
| `Condition\TicketHolder` | `final readonly class` | `ticket.holder`, au-dessus de `Participation`. |
| `Condition\DateParticipant` | `final readonly class` | `date.participant`, au-dessus de `Participation`. |
| `Condition\SelectionMember` | `final readonly class` | `selection.member`, au-dessus de `Participation`. |
| `Condition\PurchaseMade` | `final readonly class` | `purchase.made`, au-dessus de `Purchases`. |
| `Condition\SupporterActive` | `final readonly class` | `supporter.active`, au-dessus de `Supporters`. |
| `Condition\PersonTagged` | `final readonly class` | `person.tagged`, au-dessus de `PeopleTags`. |

## Ports

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Audience` | `interface` | Les membres qu'un segment peut sélectionner, par pages. |
| `Contract\Exclusions` | `interface` | La liste d'exclusion globale. |
| `Contract\Participation` | `interface` | Billets, dates et sélections. |
| `Contract\Purchases` | `interface` | Commandes. |
| `Contract\Supporters` | `interface` | Soutiens actifs. |
| `Contract\PeopleTags` | `interface` | Étiquettes internes. |
| `Contract\MembershipStore` | `interface` | Les membres des segments recalculés et la date de la dernière exécution. |

## Événements

| Type | Genre | Rôle |
|---|---|---|
| `Event\MemberEntered` | `final readonly class` | `segment`, `member`, `at`. |
| `Event\MemberLeft` | `final readonly class` | `segment`, `member`, `at`. |

## Exceptions

| Type | Genre | Rôle |
|---|---|---|
| `Exception\AudienceSegmentsException` | `interface` | Toute exception du paquet. |
| `Exception\InvalidSegment` | `final class`, `InvalidArgumentException` | Un identifiant, un nom, une définition ou un type de condition refusé. |
| `Exception\SegmentTooLarge` | `final class`, `RuntimeException` | Un calcul complet au-delà de `maxMembers`. |
| `Exception\PortContractViolated` | `final class`, `LogicException` | Une page de port hors d'ordre, trop grande ou de mauvaise forme. |
