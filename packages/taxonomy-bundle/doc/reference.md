# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilTaxonomyBundle` | `final class`, `AbstractBundle` | Alias `phil_taxonomy`; the mapping and the services |
| `Domain\Model\Tag` | entity | A tag and its slug |
| `Domain\Model\Category` | entity | A category in the tree |
| `Domain\Model\CategoryTranslation` | entity | A category's name in a language |
| `Domain\Model\Assignment` | entity | A tag or a category given to a resource |
| `Phil\Resource\ResourceReference` | `final readonly class`, from `phil/resource`, a dependency | A kind and an identifier |
| `Domain\Exception\TaxonomyException`, `InvalidTaxonomy` | exceptions | Everything the bundle throws |
| `Domain\Repository\TaxonomyRepository` | interface | The port |
| `Application\Taxonomy` | `final readonly class` | Tags, categories, assignments |
| `Application\CategoryNode` | `final readonly class` | A category in a tree |
| `Infrastructure\Doctrine\DoctrineTaxonomyRepository` | repository | The port on Doctrine ORM |
| `Infrastructure\Form\TagsType`, `TagsTransformer`, `CategoryType` | form types | Tags as text; a category from the tree |
