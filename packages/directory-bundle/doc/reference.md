# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilDirectoryBundle` | `final class`, `AbstractBundle` | Alias `phil_directory`; the configuration, what is prepended |
| `Domain\Model\Listing` | Doctrine entity | The root: genre, author, editors, who finds it (`HasIndexingConsent`) |
| `Domain\Model\ListingDimension` | Doctrine entity | A version, stage and locale of a listing |
| `Domain\ValueObject\ListingId` | `final readonly class` | The UUID of a listing |
| `Domain\Exception\ListingRefused` | `DomainException`, `DomainError` | An action the actor may not take |
| `Domain\Capability\*Capable`, `*Trait` | interfaces and traits | Contact, address, location, related, edited-by |
| `Application\Directory` | `final readonly class` | Every action on listings |
| `Application\Actor` | `final readonly class` | Who acts: a user identifier, administrator or not |
| `Application\Genre`, `Genres` | `final readonly class` | The genres of the configuration |
| `Application\Relation`, `Relations` | `final readonly class` | The relations between genres of the configuration |
| `Application\RelatedListings` | `final readonly class` | The listings related to one by one relation and one way |
| `Application\ListingPage`, `ListingVersion` | `final readonly class` | A published page, a line of the history |
| `Application\UserErasure` | interface | Erases a user's identifier from the listings and their versions |
| `Application\RelatedLookup` | interface | The listings of a genre whose live rows name a listing under a relation |
| `Application\Capability\*Pipeline` | data mapper, merger, normalizer | The five capabilities in content-bundle |
| `Infrastructure\Doctrine\ListingIdType` | Doctrine type | `phil_directory_listing_id` |
| `Infrastructure\Doctrine\*CapabilityMapping` | capability mapping | The columns of the five capabilities |
| `Infrastructure\Doctrine\DoctrineUserErasure` | `final readonly class` | `UserErasure` on the Doctrine ORM |
| `Infrastructure\Doctrine\DoctrineRelatedLookup` | `final readonly class` | `RelatedLookup` on the Doctrine ORM |
| `Infrastructure\Security\Actors` | `final readonly class` | The actor of the current request |
| `Infrastructure\Security\ListingVoter` | voter | `phil_directory.edit`, `.editors`, `.publish` |
| `Infrastructure\Form\ListingType`, `EditorsType` | form types | A listing; its editors and who finds it |
| `Infrastructure\Http\DirectoryController` | controller | The seven pages |
| `Infrastructure\Routing\DirectoryRoutes` | route loader | `phil_directory` |
| `Infrastructure\Sitemap\DirectoryUrlProvider` | URL provider | One sitemap section per genre |
| `Infrastructure\DependencyInjection\DirectoryServices` | `final class` | Registers the services |
