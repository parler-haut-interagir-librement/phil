# phil/menu-bundle

**Navigation menus and breadcrumbs for Symfony, with the visitor's current page marked.**

phil/menu-bundle builds menus as trees of immutable items. Each item has a key, a label, a route or a URL, and may
carry an icon, a description, counts, classes and attributes. The bundle marks the item the visitor is on and the
items that lead to it, from the request's route or path. Twig shows a menu as a navigation list, as cards or as a
breadcrumb, and gives the breadcrumb as a schema.org `BreadcrumbList`. It does not store menus: a builder reads them
from wherever the application keeps them. It does not count anything either, since counting is a decorator's work.

It is for Symfony applications whose navigation is more than a few fixed links: sections with counts of their
entries, cards whose attributes a script sorts or filters in the browser, a breadcrumb on every page. The templates
mark the current link with `aria-current="page"` for screen readers.

The design rests on two extension points, found by autoconfiguration. A `MenuBuilder` makes one menu from code, a
table or a file. `MenuDecorator` services then change its items in order of priority, children before their parent,
to add counts, badges, classes or children from elsewhere. Twig is optional: without it, `Menus` still gives each
menu built, decorated and marked.

## Status

| Part | State |
|---|---|
| `MenuItem` and `Menu`: immutable trees, keys, counts, attributes, breadcrumb | Implemented and tested |
| `MenuBuilder`, `MenuDecorator`, found by autoconfiguration; `Menus` | Implemented and tested |
| The current item and its ancestors, from the route or the path | Implemented and tested |
| Twig: `menu`, `menu_breadcrumb`, `menu_breadcrumb_schema`; list, cards and breadcrumb templates | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Items and menus](doc/items.md) | `MenuItem`, `Menu`, and what an item holds. |
| [Builders and decorators](doc/extension-points.md) | Making a menu, changing it, in which order. |
| [The current item](doc/current.md) | How the current item and its ancestors are told. |
| [Twig](doc/twig.md) | The functions and the three templates. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

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

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/url` 0.1.2, which requires the intl, mbstring and URI extensions;
`symfony/dependency-injection` and `symfony/http-foundation` 8.2 or later. Twig is optional; the templates use the
router's `path()`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
