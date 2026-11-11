# Builders and decorators

## `MenuBuilder`

```php
interface MenuBuilder
{
    public function name(): string;
    public function build(MenuContext $context): Menu;
}
```

A builder makes one menu, named by `name()`; two builders of a name are refused. It may read the items from code, a
table, a configuration file, and use the `MenuContext` — the route, its parameters, the path, the language.

## `MenuDecorator`

```php
interface MenuDecorator
{
    public function supports(string $menu): bool;
    public function decorate(MenuItem $item, MenuContext $context): MenuItem;
}
```

A decorator changes the items of the menus it supports. Decorators run in the order of their priority, highest
first — `#[AsTaggedItem(priority: 10)]` on the class —, each on every item, children before their parent: a
decorator that adds the counts of the children to their parent reads them already counted.

What an application puts in decorators: the counts of each section and of its new entries, from its own tables and
its own cache; classes that sort or filter cards in the browser; a badge, an icon, an attribute; children that come
from elsewhere.

## `Menus`

The service that puts them together: `get($name, $context)` builds the menu, runs the decorators, then marks the
current item. An unknown name throws `InvalidMenu`, which lists the menus there are.
