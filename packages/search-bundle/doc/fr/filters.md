<!-- phil-dev source-sha256=921a7277c75ac39acd0637728b8d23c2b8e34d86c1ffb4f2878232c54d4d9586 -->
# Filtres

Les filtres d'une page de résultats : une case par valeur de chaque facette, avec son compte ; les valeurs choisies,
chacune un lien qui la retire, et un lien qui les retire toutes ; le nombre de résultats dans une région dynamique. Ils
fonctionnent avec de simples liens et un simple formulaire `GET`, sans JavaScript ; le contrôleur Stimulus `filters`
les fait s'appliquer sur-le-champ.

## La requête

`SearchRequest` lit ce qu'un visiteur a demandé dans la chaîne de requête : les mots, `q` ; les valeurs choisies pour
chaque facette, `filter[genre][]` ; la page, `page`.

```php
use Phil\SearchBundle\Application\SearchRequest;

$asked = SearchRequest::fromQuery($request->query->all(), facets: ['genre', 'year'], perPage: 20);
$result = $search->search('games', $asked->query(['genre', 'year'], ['locale' => $request->getLocale()]));
```

| Lu | Gardé |
|---|---|
| `q` | Du texte en UTF-8 valide, ses 200 premiers caractères |
| `filter[<facet>][]` | Seulement les facettes données ; des valeurs de texte de 1 à 200 caractères, chacune une fois, 100 par facette |
| `page` | De 1 à 1000, écrite comme un nombre |

Tout le reste est écarté, pour qu'un lien copié, coupé ou forgé ouvre quand même une page. `query()` ajoute les filtres
que la page définit elle-même — une locale, un genre — avant ceux qu'un visiteur a choisis. `without($facet, $value)`,
`withoutFilters()` et `onPage()` donnent d'autres requêtes, et `queryString()` le lien qui y mène : `?` et les
paramètres, ce qui conserve le chemin de la page, sans lire aucune adresse.

## Le composant

```twig
<twig:PhilSearchFilters
    :request="request"
    :result="result"
    :labels="{genre: 'Genre'|trans, year: 'Year'|trans}"
    :valueLabels="{genre: {jdr: 'Role-playing game'|trans}}"
    results="results"
/>

<ul id="results">
    {% for game in games %}<li>{{ game.title }}</li>{% endfor %}
</ul>
```

| Propriété | Ce que c'est |
|---|---|
| `request`, `result` | Le `SearchRequest` et le `SearchResult` de la page ; obligatoires |
| `labels` | Chaque facette affichée, dans cet ordre, et son nom pour un visiteur |
| `valueLabels` | Le nom d'une valeur, par facette, quand ce n'est pas la valeur elle-même |
| `results` | L'identifiant de l'élément qui contient les résultats, que le contrôleur redessine avec les filtres |
| `id` | L'identifiant de la racine du composant, `phil-search-filters` par défaut ; conservé d'une page à l'autre |
| `action` | La destination du formulaire ; la même page quand il est vide |

Une facette sans valeur trouvée n'est pas affichée ; une valeur choisie que la recherche ne trouve plus est quand même
affichée, avec un compte de 0, pour qu'on puisse la décocher. Chaque case garde le même identifiant d'une page à
l'autre. Le compte est dit avec chaque valeur — « 2 results » — et affiché comme un nombre ; les liens qui retirent un
filtre le disent à un lecteur d'écran.

Le composant est dessiné avec les recettes Badge, Button, Checkbox et Field du kit PHIL UX, ou de tout kit qui a les
mêmes noms et les mêmes propriétés, que l'application installe avec le Toolkit de Symfony UX :

```bash
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install checkbox --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les recettes portent le nom du sous-répertoire des composants du Toolkit — avec `ui`, `ui:Checkbox` —, ou celui de
`phil_search.kit_subdirectory`. Les textes sont dans le domaine `phil_search`, en anglais et en français.

## Le contrôleur

La racine du composant porte le contrôleur `phil--search-bundle--filters`, que l'application installe avec Symfony
UX — AssetMapper ou Webpack Encore — à partir de `assets/package.json`. Avec lui :

- le bouton qui applique les filtres est caché, et une case applique son filtre dès qu'elle est cochée ;
- un lien qui retire un filtre est suivi sur place ; un clic avec une touche enfoncée, ou d'un autre bouton, est laissé
  au navigateur ;
- la page est récupérée, et les filtres et l'élément nommé par `results` sont redessinés sans rechargement ; le titre de
  la page suit ;
- la région dynamique reste dans la page et reçoit le nouveau compte, qu'un lecteur d'écran annonce ;
- le focus revient à la case ou au lien qui l'avait, sinon aux filtres, quand il était parmi eux ;
- l'adresse change avec les filtres, et un retour en arrière redessine les précédents ;
- une page qui ne peut pas être récupérée, ou une adresse d'une autre origine, est ouverte par le navigateur, comme elle
  le serait sans le contrôleur.

Le contrôleur utilise `fetch`, `DOMParser` et `history.pushState`, sans script ni style en ligne, et il s'exécute donc
sous une politique de sécurité du contenu stricte.
