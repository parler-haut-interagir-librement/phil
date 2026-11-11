# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Listings\ListingId` | `final readonly class` | The identifier of a listing: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\MemberId` | `final readonly class` | The identifier of a member: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ResponseId` | `final readonly class` | The identifier of a response: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ListingKind` | `final readonly class` | A kind of listing: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ModerationReason` | `final readonly class` | Why a listing was rejected or removed: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\Title` | `final readonly class` | A folded, bounded title: `of()`, `MAX_LENGTH`, `MAX_INPUT_BYTES` |
| `Phil\Listings\Location` | `final readonly class` | Online or an area with an exact place: `online()`, `area()`, `isOnline()`, `approximate()` |
| `Phil\Listings\CriterionType` | enum | `choice`, `range`, `flag` |
| `Phil\Listings\Criterion` | `final readonly class` | One criterion: `choice()`, `range()`, `flag()`, `meets()` |
| `Phil\Listings\Criteria` | `final readonly class` | The criteria by name: `of()`, `meets()`, `get()`, `all()`, `MAX_COUNT` |
| `Phil\Listings\Moderation` | enum | `before_publication`, `after_publication` |
| `Phil\Listings\KindPolicy` | `final readonly class` | The bounded rules of a kind: `of()`, `expiryFrom()`, `extendedFrom()`, `reminderFrom()` |
| `Phil\Listings\KindPolicies` | `final readonly class` | The policies by kind: `get()`, `has()`, `kinds()` |
| `Phil\Listings\ListingStatus` | enum | `draft`, `pending_review`, `published`, `rejected`, `hidden`, `expired`, `closed`, `removed` |
| `Phil\Listings\Listing` | `final readonly class` | A listing and its pure transitions: `draft()`, `restore()`, `submit()`, `approve()`, `reject()`, `remove()`, `report()`, `edit()`, `extend()`, `close()`, `at()`, `markReminded()`, `isVisibleAt()`, `needsReminderAt()`, `admitsAge()`, `locationFor()` |
| `Phil\Listings\ResponseStatus` | enum | `pending`, `accepted`, `declined`, `withdrawn` |
| `Phil\Listings\Response` | `final readonly class` | A response and its pure transitions: `pending()`, `restore()`, `accept()`, `decline()`, `withdraw()`, `isOpen()`, `permitsContact()` |
| `Phil\Listings\Refusal` | enum | Why a response is refused: `not_open`, `responses_closed`, `own_listing`, `blocked`, `age_unknown`, `too_young`, `already_responded`, `full`, `other_listing` |
| `Phil\Listings\Responder` | `final readonly class` | The rules across a listing and its responses: `refusal()`, `respond()`, `accept()`, `decline()`, `withdraw()` |
| `Phil\Listings\Contract\BlockList` | interface | Whether one of two members blocked the other: `isBlocked()` |
| `Phil\Listings\Contract\MemberAges` | interface | A member's age at an instant: `ageOf()` |
| `Phil\Listings\Contract\ResponseStore` | interface | Where the responses live: `countOpen()`, `hasOpen()`, `save()` |
| `Phil\Listings\Exception\ListingsException` | interface | Every exception of the library |
| `Phil\Listings\Exception\InvalidListing` | `final class` | A value the library cannot work with |
| `Phil\Listings\Exception\ForbiddenTransition` | `final class` | A change the status does not allow |
| `Phil\Listings\Exception\UnknownKind` | `final class` | A kind no policy is declared for |
| `Phil\Listings\Exception\ResponseRefused` | `final class` | A response or an acceptance refused, with its `refusal` |
