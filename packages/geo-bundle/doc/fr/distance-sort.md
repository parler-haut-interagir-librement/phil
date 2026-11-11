<!-- phil-dev source-sha256=1679231faf5455ddc2e897d51def463765b14f64cbfd54d6ac834fc948fe35b7 -->
# Trier par distance

Le contrôleur `distance-sort` trie les lieux selon leur distance au visiteur **dans le navigateur**. La position du
visiteur est demandée au navigateur, qui la demande au visiteur, et ne le quitte jamais : le serveur publie les lieux,
brouillés si leur position exacte est privée, et ne sait rien de qui est près de quoi.

```twig
<div data-controller="phil--geo-bundle--distance-sort"
     data-phil--geo-bundle--distance-sort-locate-value="false"
     data-phil--geo-bundle--distance-sort-locale-value="{{ app.request.locale }}">
    <button data-action="phil--geo-bundle--distance-sort#locate">Nearest first</button>
    <ul>
        {% for store in stores %}
            <li {{ geo_attributes(store.position, 3) }}>{{ store.name }} <span data-distance></span></li>
        {% endfor %}
    </ul>
</div>
```

| Valeur | Valeur par défaut | Sens |
|---|---|---|
| `latitude`, `longitude` | aucune | Une origine fixe : la page trie à partir d'elle tout de suite |
| `locate` | `false` | Demande la position du visiteur dès que la page se connecte |
| `system` | `metric` | `metric` ou `imperial` |
| `locale` | celle du navigateur | La locale dans laquelle les distances sont écrites : une étiquette BCP 47, ou une locale Symfony comme `fr_CA`, lue comme `fr-CA` |

- Chaque cible `item` porte `data-latitude` et `data-longitude` ; les éléments sont réordonnés dans leur parent, du plus
  proche au plus loin, et ceux qui n'ont pas de position vont à la fin, dans leur ordre.
- Un élément de l'item qui porte `data-distance` reçoit la distance, `2,7 km`.
- L'action `locate` demande la position sur demande : un bouton est plus courtois qu'une demande au chargement de la
  page.
- Le contrôleur émet `sorted`, avec l'origine dans `detail.origin`, et `denied` quand le navigateur ne donne pas de
  position ; écoutez ce dernier pour expliquer pourquoi la liste est restée telle quelle.

La distance est la même distance orthodromique que `Coordinates::distanceTo()`, dans `assets/src/distance.js`.
