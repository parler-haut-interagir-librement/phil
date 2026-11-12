<!-- phil-dev source-sha256=468f1545fd53a0626e15436b9735eb928d121e2fcf8e01945f55bf984dbdde8f -->
# Recherche

```php
$result = $search->search('games', SearchQuery::of(
    'jeu de rôle',
    ['locale' => 'fr', 'genre' => ['jdr', 'plateau']],
    limit: 20,
    offset: 40,
    facets: ['genre', 'year'],
));
```

| Partie | Nature |
|---|---|
| Le texte | Des mots qu'un document doit tous contenir, sans tenir compte de la casse ; chaque mot, ou expression, peut avoir des variantes tirées des [synonymes](synonyms.md) |
| Les filtres | Des paires champ et valeur qu'un document doit toutes avoir ; une liste de valeurs vaut n'importe laquelle d'entre elles ; un champ de liste d'un document contient la valeur parmi ses valeurs ; une valeur est comparée telle quelle, accents, barres obliques et guillemets compris ; une liste contient de 1 à 100 valeurs |
| `limit`, `offset` | Une page de 1 à 1000 documents, à partir de 0 |
| `facets` | Les champs de filtre dont les valeurs sont comptées, 20 au plus |
| `language` | La langue des mots, `fr` ou `fr-CA`, pour leurs synonymes ; par défaut celle du filtre `locale` quand c'est une seule valeur |

`SearchResult::$ids` contient les identifiants trouvés, les meilleurs d'abord, et `$total` leur nombre total. Un nom
d'index qui n'en est pas un — de 1 à 64 lettres minuscules, chiffres ou `_`, commençant par une lettre — est refusé
avec `InvalidSearch` avant qu'un adaptateur le voie : un nom tiré d'une requête n'atteint donc jamais un chemin ni une
URL.

## Facettes

`SearchResult::facet('genre')` donne les valeurs d'une facette sous forme de `FacetValue` — `$value`, toujours du
texte, et `$count` —, les plus trouvées d'abord, puis par valeur, 100 au plus ; une facette sans valeur trouvée n'en
donne aucune. Chaque facette est comptée parmi les documents que la requête trouve une fois le filtre de cette même
facette écarté : un visiteur qui a choisi `jdr` voit quand même combien `plateau` en trouverait, et le choisir élargit
les résultats au lieu de les vider.

| Adaptateur | Comment les facettes sont comptées |
|---|---|
| `dbal` | Sur les lignes que lit la recherche, en PHP |
| `meilisearch` | La distribution de facettes de Meilisearch ; une facette dont le filtre est défini est comptée par une requête de plus sans ce filtre, les requêtes étant envoyées ensemble à `/multi-search` |
| `loupe` | La distribution de facettes de Loupe, une recherche de plus sans résultats par facette dont le filtre est défini ; une facette que Loupe n'a jamais vue comme filtre ne donne aucune valeur |

## Classement

Avec `dbal`, un document est classé selon la fréquence des mots qu'il contient, et de leurs variantes, puis par
identifiant ; une recherche avec des mots, des filtres ou des facettes lit chaque ligne candidate, une recherche sans
aucun d'eux lit une page et compte le reste en SQL. Avec Meilisearch et Loupe, le classement est celui du moteur,
tolérance aux fautes de frappe comprise ; Loupe, comme `dbal`, ne trouve un document que s'il contient chaque mot.

## schema.org

```php
$data = SearchActionSchema::of('https://example.com/', 'https://example.com/search?q={search_term_string}', 'Le site');
```

Un `WebSite` dont la `potentialAction` est une `SearchAction` sur la page de résultats du site lui-même, pour qu'un
moteur puisse offrir une boîte de recherche qui y mène. L'URL des résultats contient `{search_term_string}` à l'endroit
où vont les mots ; une URL sans lui est refusée. Imprimez-la sur la page d'accueil en JSON-LD, avec `JSON_HEX_TAG`.
