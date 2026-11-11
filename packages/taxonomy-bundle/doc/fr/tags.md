<!-- phil-dev source-sha256=6b4864f63f5cb47b9b890e69d37d39d33d24749248aaba0edc9a03fa7df5f498 -->
# Étiquettes

Une étiquette est un nom d'au plus 100 caractères et son *slug* — `Jeux de rôle` donne `jeux-de-role` —, unique : deux
noms qui ne diffèrent que par la casse ou les accents forment une seule étiquette.

Le *slug* est produit par le générateur de *slugs* de la taxonomie : par défaut, le générateur par translittération de
`phil/slug`, d'au plus 128 caractères, coupé à la fin d'un mot. La clé `slugger` en nomme un autre — un générateur nommé
de `phil/slug-bundle` (`phil_slug.slugger.tags`), ou un générateur décoré — et ce même générateur produit les clés de
catégorie. Un *slug* de plus de 128 caractères, ou un nom dont on ne peut tirer aucun *slug*, est une
`InvalidTaxonomy`.

| Méthode | Ce qu'elle fait |
|---|---|
| `tag($name)` | L'étiquette de ce nom, créée si elle est nouvelle |
| `setTags($resource, $names)` | Donne à la ressource exactement ces étiquettes, au plus `Taxonomy::MAX_TAGS` (100), sinon `InvalidTaxonomy` avant toute requête ; les retourne par nom |
| `tagsOf($resource)` | Ses étiquettes, par nom |
| `taggedWith($tag, $kind)` | Les ressources qui portent cette étiquette, d'une seule sorte si elle est donnée |
| `renameTag($tag, $name)` | La renomme ; une autre étiquette de ce nom est une `InvalidTaxonomy` : fusionnez-les plutôt |
| `mergeTags($from, $into)` | Donne à la seconde chaque ressource de la première, une seule fois, puis retire la première |

```php
$taxonomy->setTags(ResourceReference::of('page', '42'), ['Jeux', 'soirée']);
$taxonomy->mergeTags($taxonomy->tag('jdr'), $taxonomy->tag('rpg'));
```
