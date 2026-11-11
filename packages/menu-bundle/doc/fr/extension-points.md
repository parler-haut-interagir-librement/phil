<!-- phil-dev source-sha256=1fdc485d46b760c8abb0141b9c7d76ebed5084119398be162f71677a8b19784c -->
# Constructeurs et décorateurs

## `MenuBuilder`

```php
interface MenuBuilder
{
    public function name(): string;
    public function build(MenuContext $context): Menu;
}
```

Un constructeur fabrique un menu, nommé par `name()` ; deux constructeurs du même nom sont refusés. Il peut lire les
éléments dans du code, une table ou un fichier de configuration, et utiliser le `MenuContext` : la route, ses
paramètres, le chemin, la langue.

## `MenuDecorator`

```php
interface MenuDecorator
{
    public function supports(string $menu): bool;
    public function decorate(MenuItem $item, MenuContext $context): MenuItem;
}
```

Un décorateur modifie les éléments des menus qu'il prend en charge. Les décorateurs s'exécutent par ordre de priorité,
la plus élevée en premier (`#[AsTaggedItem(priority: 10)]` sur la classe), chacun sur chaque élément, les enfants avant
leur parent : un décorateur qui ajoute les compteurs des enfants à leur parent les lit déjà comptés.

Ce qu'une application met dans des décorateurs : le nombre d'entrées de chaque section et de ses nouvelles entrées, à
partir de ses propres tables et de son propre cache ; des classes qui trient ou filtrent les cartes dans le navigateur ;
un badge, une icône, un attribut ; des enfants qui viennent d'ailleurs.

## `Menus`

Le service qui les assemble : `get($name, $context)` construit le menu, exécute les décorateurs, puis marque l'élément
courant. Un nom inconnu lance `InvalidMenu`, qui liste les menus existants.
