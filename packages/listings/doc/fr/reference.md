<!-- phil-dev source-sha256=1e704941f9a136e3d1a6d24d7e29ef5052acebdc28bb778edb63f6911fa5c1ab -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Listings\ListingId` | `final readonly class` | L'identifiant d'une annonce : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\MemberId` | `final readonly class` | L'identifiant d'un membre : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ResponseId` | `final readonly class` | L'identifiant d'une réponse : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ListingKind` | `final readonly class` | Un genre d'annonce : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\ModerationReason` | `final readonly class` | Pourquoi une annonce a été rejetée ou retirée : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\Listings\Title` | `final readonly class` | Un titre replié et borné : `of()`, `MAX_LENGTH`, `MAX_INPUT_BYTES` |
| `Phil\Listings\Location` | `final readonly class` | En ligne, ou une zone avec un lieu exact : `online()`, `area()`, `isOnline()`, `approximate()` |
| `Phil\Listings\CriterionType` | enum | `choice`, `range`, `flag` |
| `Phil\Listings\Criterion` | `final readonly class` | Un critère : `choice()`, `range()`, `flag()`, `meets()` |
| `Phil\Listings\Criteria` | `final readonly class` | Les critères par nom : `of()`, `meets()`, `get()`, `all()`, `MAX_COUNT` |
| `Phil\Listings\Moderation` | enum | `before_publication`, `after_publication` |
| `Phil\Listings\KindPolicy` | `final readonly class` | Les règles bornées d'un genre : `of()`, `expiryFrom()`, `extendedFrom()`, `reminderFrom()` |
| `Phil\Listings\KindPolicies` | `final readonly class` | Les politiques par genre : `get()`, `has()`, `kinds()` |
| `Phil\Listings\ListingStatus` | enum | `draft`, `pending_review`, `published`, `rejected`, `hidden`, `expired`, `closed`, `removed` |
| `Phil\Listings\Listing` | `final readonly class` | Une annonce et ses transitions pures : `draft()`, `restore()`, `submit()`, `approve()`, `reject()`, `remove()`, `report()`, `edit()`, `extend()`, `close()`, `at()`, `markReminded()`, `isVisibleAt()`, `needsReminderAt()`, `admitsAge()`, `locationFor()` |
| `Phil\Listings\ResponseStatus` | enum | `pending`, `accepted`, `declined`, `withdrawn` |
| `Phil\Listings\Response` | `final readonly class` | Une réponse et ses transitions pures : `pending()`, `restore()`, `accept()`, `decline()`, `withdraw()`, `isOpen()`, `permitsContact()` |
| `Phil\Listings\Refusal` | enum | Pourquoi une réponse est refusée : `not_open`, `responses_closed`, `own_listing`, `blocked`, `age_unknown`, `too_young`, `already_responded`, `full`, `other_listing` |
| `Phil\Listings\Responder` | `final readonly class` | Les règles qui touchent une annonce et ses réponses : `refusal()`, `respond()`, `accept()`, `decline()`, `withdraw()` |
| `Phil\Listings\Contract\BlockList` | interface | Si l'un de deux membres a bloqué l'autre : `isBlocked()` |
| `Phil\Listings\Contract\MemberAges` | interface | L'âge d'un membre à un instant donné : `ageOf()` |
| `Phil\Listings\Contract\ResponseStore` | interface | L'endroit où vivent les réponses : `countOpen()`, `hasOpen()`, `save()` |
| `Phil\Listings\Exception\ListingsException` | interface | Toutes les exceptions de la bibliothèque |
| `Phil\Listings\Exception\InvalidListing` | `final class` | Une valeur que la bibliothèque ne peut pas utiliser |
| `Phil\Listings\Exception\ForbiddenTransition` | `final class` | Un changement que le statut ne permet pas |
| `Phil\Listings\Exception\UnknownKind` | `final class` | Un genre pour lequel aucune politique n'est déclarée |
| `Phil\Listings\Exception\ResponseRefused` | `final class` | Une réponse ou une acceptation refusée, avec son `refusal` |
