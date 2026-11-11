<!-- phil-dev source-sha256=9bab56275987c0355d28e6d509c3be5e038e9759a0a4d31b5c438a6185be2d65 -->
# Twig

| Nom | Genre | Donne |
|---|---|---|
| `geo_distance(from, to)` | fonction | La `Distance` entre deux `Coordinates` ; `null` quand l'une manque |
| `format_distance(locale = null, system = 'metric')` | filtre | `850 m`, `12,3 km` en `fr_CA` ; `7.7 mi` avec `imperial` ; vide pour `null` |
| `geo_attributes(position, decimals = 7)` | fonction, HTML sûr | Les attributs d'un lieu que trie le contrôleur `distance-sort` : sa cible et sa position, avec ce nombre de décimales |

```twig
{{ geo_distance(here, store.position)|format_distance }}
<li {{ geo_attributes(store.position, 3) }}>{{ store.name }}</li>
```

`format_distance` construit un formateur de nombres par locale et garde ceux de 64 locales au plus : les locales tirées
des requêtes ne peuvent donc pas faire croître la mémoire d'un worker qui vit d'une requête à l'autre.

## schema.org

| Fonction | Donne |
|---|---|
| `geo_schema(coordinates)` | Les coordonnées sous forme de `GeoCoordinates` de schema.org, ou `null` |

Les données se composent en un document JSON-LD affiché par le gabarit :

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` écrit `<` et `>` sous forme d'échappements : aucune valeur ne peut donc fermer l'élément script.
