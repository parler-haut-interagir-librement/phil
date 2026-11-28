<!-- phil-dev source-sha256=fca591d6dfee2e5d8652ee21e8ab9a940e2402b009484f8daae4858de4d77d06 -->
# Pointage

`Neighbourhood::compute()` construit la liste d'un élément en trois parties, dans cet ordre :

1. les **épingles** de `Contract\Pins`, dans l'ordre fixé par la rédaction ;
2. les **relations typées** de `Contract\TypedRelations` : les accessoires et les versions supérieures qui partent de
   l'élément, puis les compléments qui en partent ou y arrivent — voir [Produits](products.md) ;
3. les **candidats pointés**, du score le plus haut au plus bas, et par identifiant à égalité.

Un voisin apparaît une fois, sous sa première partie. Un élément épinglé ou relié que `ItemSource::item()` ne retourne
pas — non publié, retiré — ou qui est dans une autre langue est omis. `RelevanceRules::$limit` borne toute la liste.

## Le score d'un candidat

Le score additionne :

- le score de chaque indice de `RelevanceRules::$indices` ;
- `similarWeight` × la similarité de `Contract\SimilarItems`, plafonnée à 1 ;
- pour chaque compte de paire d'au moins `minimumPairCount`, le poids de son signal × son compte divisé par le compte le
  plus élevé de l'élément — la paire la plus forte obtient le plein poids.

Un candidat dont le total est sous `threshold` est écarté. Chaque voisin garde ses `reasons`, la contribution la plus
forte d'abord, pour qu'un gabarit puisse dire pourquoi il est affiché. Les scores sont arrondis à six décimales.

## Les indices

| Indice | Score |
|---|---|
| `KeywordIndex($name, $weight, $broaderFactor = 0.0)` | `weight` par mot-clé que les deux éléments détiennent dans l'indice `$name`, plus `broaderFactor × weight` par terme plus large qu'ils partagent en outre |
| `DateProximity($weight, $windowDays = 365)` | `weight` pour le même instant, décroissant en ligne droite jusqu'à 0 à `windowDays` d'écart ; 0 quand l'un des éléments n'a pas de date |

Chaque indice donne le même score dans les deux sens : les voisins d'un nouvel élément sont exactement les éléments dont
les listes peuvent maintenant le contenir, ce que `RelatedContent::published()` recalcule. Un indice personnalisé
conserve cette propriété — voir [Points d'extension](extension-points.md).

Les mots-clés sont comparés sans espaces aux extrémités, en minuscules, chacun une seule fois. Un élément détient au
plus 32 indices et 200 mots-clés par indice.

### Les règles pour le contenu

`RelevanceRules::forContent()` lit les mots-clés `topics`, `authors` et `series` :

| Indice | Poids |
|---|---|
| `topics`, termes plus larges à 0,25 | 100 |
| `authors` | 40 |
| `series` | 200 |
| la date, à moins de 365 jours | 20 |

avec un seuil de 80 : un sujet partagé suffit, un auteur seul ne suffit pas, une série suffit toujours.

### Termes plus larges

`TaxonomyExpander` demande à `Contract\Taxonomy` les termes plus larges des mots-clés des indices qu'on lui donne et les
ajoute à l'élément. Deux éléments classés sous « fantasy épique » et « fantasy sombre », dont le terme plus large est
« fantasy », partagent alors ce terme à `broaderFactor`, sans partager un sujet :

```php
use Phil\RelatedContent\TaxonomyExpander;

$item = new TaxonomyExpander($taxonomy, ['topics'])->expand($item);
```

L'expanseur garde les 200 premiers termes plus larges d'un indice.

## Candidats plus récents

Avec `includeNewer: false`, un candidat daté après l'élément est omis, pour qu'une page d'archive ne pointe que vers le
passé. Un candidat ou un élément sans date n'est jamais omis pour cette raison.

## Les bornes

| Règle | Valeur par défaut | Bornes |
|---|---|---|
| `threshold` | 80 | 0 ou plus |
| `limit` | 10 | 1 à 100 |
| `maxCandidates` : les candidats lus de `ItemSource::candidates()` | 500 | 1 à 10 000 |
| `similarWeight` | 100 | 0 ou plus |
| `boughtTogetherWeight` | 100 | 0 ou plus |
| `viewedNextWeight` | 50 | 0 ou plus |
| `minimumPairCount` | 3 | 1 à 1 000 000 |
| `maxRecomputes` : les éléments recalculés à la fois | 100 | 1 à 10 000 |

Un calcul lit au plus `maxCandidates` candidats, `limit` éléments semblables et `limit` paires de chaque signal : son
coût ne croît pas avec la taille du site, et le banc d'essai de [Tests](testing.md) le mesure.
