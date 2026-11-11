<!-- phil-dev source-sha256=34b518839fb96d994a0e49efa5bdc0bc56cddbb82f3a2452ac6c7f972e670d03 -->
# phil/menu-bundle

**Des menus de navigation et des fils d'Ariane pour Symfony, avec la page courante du visiteur marquée.**

phil/menu-bundle construit les menus sous forme d'arbres d'éléments immuables. Chaque élément a une clé, un libellé, une
route ou une URL, et peut porter une icône, une description, des compteurs, des classes et des attributs. Le bundle
marque l'élément où se trouve le visiteur et les éléments qui y mènent, à partir de la route ou du chemin de la requête.
Twig affiche un menu sous forme de liste de navigation, de cartes ou de fil d'Ariane, et donne le fil d'Ariane sous
forme de `BreadcrumbList` de schema.org. Le bundle ne stocke pas les menus : un constructeur les lit là où l'application
les conserve. Il ne compte rien non plus, car compter est le travail d'un décorateur.

Il s'adresse aux applications Symfony dont la navigation dépasse quelques liens fixes : des sections avec le nombre de
leurs entrées, des cartes dont un script trie ou filtre les attributs dans le navigateur, un fil d'Ariane sur chaque
page. Les gabarits marquent le lien courant avec `aria-current="page"` pour les lecteurs d'écran.

La conception repose sur deux points d'extension, trouvés par configuration automatique. Un `MenuBuilder` fabrique un
menu à partir de code, d'une table ou d'un fichier. Des services `MenuDecorator` modifient ensuite ses éléments par
ordre de priorité, les enfants avant leur parent, pour ajouter des compteurs, des badges, des classes ou des enfants
venus d'ailleurs. Twig est facultatif : sans lui, `Menus` donne quand même chaque menu construit, décoré et marqué.

## État

| Partie | État |
|---|---|
| `MenuItem` et `Menu` : arbres immuables, clés, compteurs, attributs, fil d'Ariane | Implémenté et testé |
| `MenuBuilder`, `MenuDecorator`, trouvés par configuration automatique ; `Menus` | Implémenté et testé |
| L'élément courant et ses ancêtres, à partir de la route ou du chemin | Implémenté et testé |
| Twig : `menu`, `menu_breadcrumb`, `menu_breadcrumb_schema` ; gabarits de liste, de cartes et de fil d'Ariane | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'enregistrement. |
| [Éléments et menus](doc/fr/items.md) | `MenuItem`, `Menu`, et ce que contient un élément. |
| [Constructeurs et décorateurs](doc/fr/extension-points.md) | Fabriquer un menu, le modifier, dans quel ordre. |
| [L'élément courant](doc/fr/current.md) | Comment l'élément courant et ses ancêtres sont reconnus. |
| [Twig](doc/fr/twig.md) | Les fonctions et les trois gabarits. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/menu-bundle
```

```php
final class MainMenu implements MenuBuilder
{
    public function name(): string
    {
        return 'main';
    }

    public function build(MenuContext $context): Menu
    {
        return Menu::of('main', [
            MenuItem::of('home', 'Home')->withRoute('home'),
            MenuItem::of('games', 'Games')->withRoute('games')->withChildren(
                MenuItem::of('chess', 'Chess')->withRoute('game', ['slug' => 'chess']),
            ),
        ]);
    }
}
```

```twig
{% include '@PhilMenu/menu.html.twig' with {menu: menu('main')} %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/url` 0.1.3, qui requiert les extensions intl, mbstring et URI ;
`symfony/config`, `symfony/dependency-injection` et `symfony/http-foundation` 8.2 ou plus récent. Twig est facultatif ;
les gabarits utilisent le `path()` du routeur.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
