<!-- phil-dev source-sha256=9d0f0d98ae421d0ec2afda93c5a190532f7a3713f44683efa41cfd3fc24a02ee -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilTaxonomyBundle` | `final class`, `AbstractBundle` | Alias `phil_taxonomy` ; l'association des entités et les services |
| `Domain\Model\Tag` | entité | Une étiquette et son *slug* |
| `Domain\Model\Category` | entité | Une catégorie dans l'arbre |
| `Domain\Model\CategoryTranslation` | entité | Le nom d'une catégorie dans une langue |
| `Domain\Model\Assignment` | entité | Une étiquette ou une catégorie attribuée à une ressource |
| `Phil\Resource\ResourceReference` | `final readonly class`, de `phil/resource`, une dépendance | Une sorte et un identifiant |
| `Domain\Exception\TaxonomyException`, `InvalidTaxonomy` | exceptions | Tout ce que lève le bundle |
| `Domain\Repository\TaxonomyRepository` | interface | Le port |
| `Application\Taxonomy` | `final readonly class` | Étiquettes, catégories, attributions |
| `Application\CategoryNode` | `final readonly class` | Une catégorie dans un arbre |
| `Infrastructure\Doctrine\DoctrineTaxonomyRepository` | dépôt | Le port sur Doctrine ORM |
| `Infrastructure\Doctrine\BinaryKeysListener` | écouteur | Les clés des tables en `utf8mb4_bin` sur MySQL et MariaDB, au `loadClassMetadata` de l'ORM ([installation.md](installation.md#collation-des-clés)) |
| `Infrastructure\Form\TagsType`, `TagsTransformer`, `CategoryType` | types de formulaire | Les étiquettes en texte ; une catégorie tirée de l'arbre |
