# Reference

Every public type of the package, with its role. Names are relative to `Phil\AudienceSegments`.

## Segments

| Type | Kind | Role |
|---|---|---|
| `Segment` | `final readonly class` | `id`, `name` and `rule` of a segment; refuses forbidden condition types; `MEMBER`, `FORBIDDEN_WORDS`. |
| `SegmentJson` | `final readonly class` | The query language: `read(string $id, string $json)`, `fromArray()`, `write()`, `toArray()`, `fingerprint()`. |
| `AudienceConditions` | `final class` | `types(?Participation, ?Purchases, ?Supporters, ?PeopleTags): ConditionTypes`. |
| `Segments` | `final readonly class` | `check(Segment, string $member): Membership`, `page(Segment, ?string $after, int $limit): MemberPage`, `freeze(Segment): FrozenList`, `walk(Segment)`. |
| `Recalculation` | `final readonly class` | `recalculate(Segment): RecalculationReport`. |
| `Membership` | `final readonly class` | `member`, `excluded`, `result`; `isMember()`. |
| `MemberPage` | `final readonly class` | `members`, `next`. |
| `FrozenList` | `final readonly class` | `segment`, `name`, `fingerprint`, `frozenAt`, `members`; `count()`, `contains()`, `page()`. |
| `RecalculationReport` | `final readonly class` | `segment`, `members`, `entered`, `left`, `at`. |
| `PurchaseFilter` | `final readonly class` | `skus`, `products`, `orderStates`. |

## Condition types

| Type | Kind | Role |
|---|---|---|
| `Condition\TicketHolder` | `final readonly class` | `ticket.holder`, over `Participation`. |
| `Condition\DateParticipant` | `final readonly class` | `date.participant`, over `Participation`. |
| `Condition\SelectionMember` | `final readonly class` | `selection.member`, over `Participation`. |
| `Condition\PurchaseMade` | `final readonly class` | `purchase.made`, over `Purchases`. |
| `Condition\SupporterActive` | `final readonly class` | `supporter.active`, over `Supporters`. |
| `Condition\PersonTagged` | `final readonly class` | `person.tagged`, over `PeopleTags`. |

## Ports

| Type | Kind | Role |
|---|---|---|
| `Contract\Audience` | `interface` | The members a segment may select, by pages. |
| `Contract\Exclusions` | `interface` | The global exclusion list. |
| `Contract\Participation` | `interface` | Tickets, dates and selections. |
| `Contract\Purchases` | `interface` | Orders. |
| `Contract\Supporters` | `interface` | Active supporters. |
| `Contract\PeopleTags` | `interface` | Internal tags. |
| `Contract\MembershipStore` | `interface` | The members of recalculated segments and the date of the last run. |

## Events

| Type | Kind | Role |
|---|---|---|
| `Event\MemberEntered` | `final readonly class` | `segment`, `member`, `at`. |
| `Event\MemberLeft` | `final readonly class` | `segment`, `member`, `at`. |

## Exceptions

| Type | Kind | Role |
|---|---|---|
| `Exception\AudienceSegmentsException` | `interface` | Every exception of the package. |
| `Exception\InvalidSegment` | `final class`, `InvalidArgumentException` | An identifier, a name, a definition or a condition type refused. |
| `Exception\SegmentTooLarge` | `final class`, `RuntimeException` | A full computation past `maxMembers`. |
| `Exception\PortContractViolated` | `final class`, `LogicException` | A port page out of order, too large or out of form. |
