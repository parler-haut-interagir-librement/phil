<!-- phil-dev source-sha256=2bb4a013637e6a0d80daf9468682809272d4b5cea6118a8fb425d498a10e193f -->
# Éléments et menus

## `MenuItem`

```php
MenuItem::of('games', 'menu.games')       // a key unique among its siblings, and a label
    ->withRoute('games')                   // or ->withUrl('https://…'): the one replaces the other
    ->withIcon('icon-dice')
    ->withDescription('Every game, from chess to go.')
    ->withCount(120, 4)                    // all of them, and the new ones
    ->withUpdatedAt(new DateTimeImmutable('2026-11-03'))
    ->withClass('card', 'wide')            // each class once
    ->withAttribute('data-sort', 'name')   // null takes an attribute away
    ->withChildren(MenuItem::of('chess', 'menu.chess')->withRoute('game', ['slug' => 'chess']));
```

Un élément est immuable : chaque `with…()` en donne un nouveau. Une clé compte jusqu'à 100 lettres, chiffres, `_`, `-`
et `.` ; un libellé n'est pas vide ; les compteurs sont nuls ou positifs, les nouveaux n'excédant pas le total ; les
enfants ont des clés différentes. Sinon, `InvalidMenu` est lancé. `find()` atteint un descendant par sa clé ; `map()`
modifie les enfants de l'élément, puis l'élément lui-même : un élément dont les enfants reviennent identiques est
transmis tel quel, et les clés des enfants ne sont vérifiées de nouveau que si l'un d'eux a été renommé.

Les gabarits affichent une adresse dans `href` et un attribut sur l'élément HTML de l'élément de menu, de sorte que les
deux sont vérifiés quand ils sont définis, peu importe d'où le constructeur les a lus. Une adresse est sur le site (un
chemin, `/games`, ou une référence relative à la page, `#top`, `?page=2`) ou a l'un des schémas `http`, `https`,
`mailto` et `tel`. `javascript:`, `data:` et tout autre schéma sont refusés, tout comme une adresse qui contient une
espace ou un caractère de contrôle, que les navigateurs retirent d'un schéma avant de le lire. Le nom d'un attribut est
une lettre, puis jusqu'à 99 lettres, chiffres, `_`, `-`, `.` et `:`, et ne commence pas par `on`, comme le font tous les
gestionnaires d'événements.

`current` et `currentAncestor` sont définis par `Menus` (voir [l'élément courant](current.md)) ; `isActive()` vaut l'un
ou l'autre.

## `Menu`

```php
Menu::of('main', [$home, $games], translationDomain: 'messages');
```

Un nom, des éléments de premier niveau aux clés différentes, et le domaine de traduction des libellés ; `null` les
affiche tels quels. `find()` atteint n'importe quel élément ; `breadcrumb()` donne les éléments du sommet jusqu'à
l'élément courant ; `map()` modifie chaque élément, les enfants en premier.
