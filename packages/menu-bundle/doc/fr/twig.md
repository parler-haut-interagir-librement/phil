<!-- phil-dev source-sha256=e306a2a31e513790204bf931287bea8d1075f4c8d7c750f4c2095b1ba1a0f450 -->
# Twig

| Fonction | Donne |
|---|---|
| `menu(name)` | Le `Menu`, construit, décoré et marqué pour la requête |
| `menu_breadcrumb(name)` | Les éléments du sommet jusqu'à l'élément courant |
| `menu_breadcrumb_schema(name)` | Le fil d'Ariane sous forme de `BreadcrumbList` de schema.org |

Un menu est construit et décoré une seule fois par requête et par nom : une page qui affiche la liste, le fil d'Ariane
et son JSON-LD exécute le constructeur et chaque décorateur une seule fois.

## Les gabarits

```twig
{% set menu = menu('main') %}
{% include '@PhilMenu/menu.html.twig' %}
{% include '@PhilMenu/breadcrumb.html.twig' %}
{% include '@PhilMenu/cards.html.twig' with {items: menu.find('games').children} %}
```

| Gabarit | Affiche |
|---|---|
| `menu.html.twig` | Un `<nav>` de listes imbriquées ; le lien courant a `aria-current="page"`, ses ancêtres `data-active-trail` ; les attributs d'un élément sont ceux de son `<li>` |
| `breadcrumb.html.twig` | Un `<nav>` du parcours, le dernier élément ayant `aria-current="page"` ; rien quand aucun élément n'est courant |
| `cards.html.twig` | Les éléments, ou les `items` donnés, sous forme de cartes : icône sous forme de classe, libellé, description, compteurs, dernière modification |

Les libellés sont traduits dans le domaine du menu ; les mots propres aux gabarits (*Breadcrumb*, les compteurs) sont
dans le domaine `phil_menu`, en anglais et en français. Une application remplace un gabarit dans
`templates/bundles/PhilMenuBundle/`.

## schema.org

`menu_breadcrumb_schema(name)` donne le fil d'Ariane d'un menu sous forme de `BreadcrumbList` de schema.org : chaque
élément est un `ListItem` à sa position, avec son nom et son URL absolue, comme les lisent les moteurs de recherche. Le
nom est le libellé traduit dans le domaine du menu, tel que l'affichent les gabarits. L'URL d'un élément avec une route
est générée par le routeur, sous forme absolue ; une adresse sur le site, `/about`, reçoit le schéma et l'hôte de la
requête ; une adresse sur le Web est conservée. Un élément dont l'adresse est relative à la page, `#top`, n'a pas d'URL
dans la liste. Le traducteur et le routeur sont les services `translator` et `router` de l'application, quand elle les
a : sans traducteur, le libellé sert de nom, et sans routeur, un élément avec une route n'a pas d'URL.

`BreadcrumbSchema::of($items, $name, $url)` construit la même liste hors de Twig, à partir de la façon de nommer un
élément et de son URL absolue.

```twig
<script type="application/ld+json">{{ menu_breadcrumb_schema('main')|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```
