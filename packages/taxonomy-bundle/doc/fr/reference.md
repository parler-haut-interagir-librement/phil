<!-- phil-dev source-sha256=64caf9a8266502ce5015fbfc0615bf1ca081793c6fc628d523736285a0482371 -->
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
| `Infrastructure\Form\TagsType`, `TagsTransformer`, `CategoryType` | types de formulaire | Les étiquettes en texte ; une catégorie tirée de l'arbre |
