<!-- phil-dev source-sha256=40d7d6316b25fe2f55038383603d7c6837e731bc28c751507df071566a90a6d5 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

| Pour | Faire |
|---|---|
| Donner au moteur les éléments publiés | Implémenter `Contract\ItemSource` |
| Compter les termes frères d'un vocabulaire | Implémenter `Contract\Taxonomy`, et passer les éléments par `TaxonomyExpander` |
| Laisser la rédaction épingler des voisins | Implémenter `Contract\Pins` |
| Laisser les marchands relier des produits | Implémenter `Contract\TypedRelations` |
| Ajouter la similarité d'un moteur de recherche | Implémenter `Contract\SimilarItems` |
| Conserver les comptes de paires en base de données | Implémenter `Contract\PairCounts` |
| Conserver les voisins en base de données ou dans un cache | Implémenter `Contract\RelatedStore` |
| Pointer un autre indice | Implémenter `Contract\Index`, et l'ajouter à `RelevanceRules::$indices` |
| Changer un poids, le seuil ou une borne | Le passer à `RelevanceRules` |

Chaque implémentation ne conserve rien d'une requête à l'autre, de sorte qu'une même instance serve toutes les requêtes
d'un *worker* ; les implémentations en mémoire du paquet implémentent `ResetInterface` pour cette raison.

## Les ports

| Port | Contrat |
|---|---|
| `ItemSource::item(string $id): ?Item` | L'élément tel que les lecteurs peuvent le voir maintenant ; nul quand il n'est pas publié, qu'il est retiré ou inconnu. Le moteur vérifie que l'élément retourné porte l'identifiant demandé. |
| `ItemSource::candidates(Item $item): iterable<Item>` | Les éléments publiés qui valent la comparaison, d'ordinaire ceux qui partagent un mot-clé, les plus récents d'abord ; lus paresseusement, au plus `maxCandidates`. |
| `Taxonomy::broader(string $index, list<string> $terms): list<string>` | Les termes plus larges des termes, sans les termes eux-mêmes. |
| `Pins::pinned(string $id): list<string>` | Les identifiants choisis par la rédaction, dans l'ordre. |
| `TypedRelations::from(string $id)`, `to(string $id)` | Les relations qui partent de l'élément, et celles qui y arrivent. |
| `SimilarItems::similar(Item $item, int $limit): list<Similarity>` | Jusqu'à `$limit` éléments avec une similarité de 0 à 1. |
| `PairCounts::add()`, `top()`, `forget()` | Des comptes anonymes par paire ; `top()` du plus élevé au plus bas. |
| `RelatedStore::save()`, `find()`, `remove()`, `pointingAt()` | Les ensembles conservés, et les éléments dont l'ensemble contient un élément donné. |

## Écrire un indice

Un indice retourne un score pondéré, 0 quand son indice ne dit rien, et le même score dans les deux sens : `score($a,
$b)` égale `score($b, $a)`. `RelatedContent::published()` s'y fie pour trouver les éléments dont les listes changent.

```php
use Phil\RelatedContent\Contract\Index;
use Phil\RelatedContent\Item;

final readonly class SameVenue implements Index
{
    public function __construct(private float $weight)
    {
    }

    public function score(Item $item, Item $candidate): float
    {
        $venues = array_intersect_key($item->keywordSet('venue'), $candidate->keywordSet('venue'));

        return [] === $venues ? 0.0 : $this->weight;
    }
}
```

Un indice s'exécute une fois par candidat, jusqu'à `maxCandidates` fois par calcul : il lit les éléments qu'on lui
donne et n'appelle aucun service.

## Similarité de recherche

phil/search-bundle, quand son moteur trouve des documents semblables, s'adapte derrière `SimilarItems` : l'adaptateur
demande au moteur les documents les plus proches de l'élément et retourne leurs identifiants avec un score de 0 à 1.
Sans un tel moteur, le port reste vide et seuls les indices pointent.
