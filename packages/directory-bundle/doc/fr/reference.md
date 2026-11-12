<!-- phil-dev source-sha256=e457ce86d795ef357a442d8bb376216102b29173889d6c176c460c8c055d55a1 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `PhilDirectoryBundle` | `final class`, `AbstractBundle` | Alias `phil_directory` ; la configuration, ce qui est ajouté au préalable |
| `Domain\Model\Listing` | entité Doctrine | La racine : genre, auteur, rédacteurs, qui la trouve (`HasIndexingConsent`) |
| `Domain\Model\ListingDimension` | entité Doctrine | Une version, une étape et une locale d'une fiche |
| `Domain\ValueObject\ListingId` | `final readonly class` | L'UUID d'une fiche |
| `Domain\Exception\ListingRefused` | `DomainException`, `DomainError` | Une action que l'acteur n'a pas le droit de faire |
| `Domain\Capability\*Capable`, `*Trait` | interfaces et traits | Contact, adresse, position, liens, auteur de la version |
| `Application\Directory` | `final readonly class` | Chaque action sur les fiches |
| `Application\Actor` | `final readonly class` | Qui agit : un identifiant d'utilisateur, administrateur ou non |
| `Application\Genre`, `Genres` | `final readonly class` | Les genres de la configuration |
| `Application\Relation`, `Relations` | `final readonly class` | Les relations entre genres de la configuration |
| `Application\RelatedListings` | `final readonly class` | Les fiches liées à une fiche par une relation, dans un sens |
| `Application\ListingPage`, `ListingVersion` | `final readonly class` | Une page publiée, une ligne de l'historique |
| `Application\UserErasure` | interface | Efface l'identifiant d'un utilisateur des fiches et de leurs versions |
| `Application\RelatedLookup` | interface | Les fiches d'un genre dont les lignes actives nomment une fiche sous une relation |
| `Application\Capability\*Pipeline` | association de données, fusion, normalisation | Les cinq capacités dans content-bundle |
| `Infrastructure\Doctrine\ListingIdType` | type Doctrine | `phil_directory_listing_id` |
| `Infrastructure\Doctrine\*CapabilityMapping` | association de capacité | Les colonnes des cinq capacités |
| `Infrastructure\Doctrine\DoctrineUserErasure` | `final readonly class` | `UserErasure` sur l'ORM Doctrine |
| `Infrastructure\Doctrine\DoctrineRelatedLookup` | `final readonly class` | `RelatedLookup` sur l'ORM Doctrine |
| `Infrastructure\Security\Actors` | `final readonly class` | L'acteur de la requête courante |
| `Infrastructure\Security\ListingVoter` | *voter* | `phil_directory.edit`, `.editors`, `.publish` |
| `Infrastructure\Form\ListingType`, `EditorsType` | types de formulaire | Une fiche ; ses rédacteurs et qui la trouve |
| `Infrastructure\Http\DirectoryController` | contrôleur | Les sept pages |
| `Infrastructure\Routing\DirectoryRoutes` | chargeur de routes | `phil_directory` |
| `Infrastructure\Sitemap\DirectoryUrlProvider` | fournisseur d'URL | Une section du plan du site par genre |
| `Infrastructure\DependencyInjection\DirectoryServices` | `final class` | Enregistre les services |
