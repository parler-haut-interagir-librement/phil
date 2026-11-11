# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilMenuBundle` | `final class`, `AbstractBundle` | Alias `phil_menu`; autoconfiguration of builders and decorators |
| `Domain\MenuItem` | `final readonly class` | An entry of a menu |
| `Domain\Menu` | `final readonly class` | A named menu, its breadcrumb |
| `Domain\InvalidMenu` | `final class`, `InvalidArgumentException` | An item or a menu that cannot be |
| `Application\MenuBuilder` | interface | Makes a menu |
| `Application\MenuDecorator` | interface | Changes the items of menus |
| `Application\MenuContext` | `final readonly class` | Where the visitor is |
| `Application\Menus` | `final readonly class` | Builds, decorates and marks the menus |
| `Application\BreadcrumbSchema` | `final class` | A breadcrumb as a schema.org `BreadcrumbList`, its names and URLs given |
| `Infrastructure\Twig\MenuExtension` | Twig extension | `menu`, `menu_breadcrumb`, `menu_breadcrumb_schema` |
| `templates/menu.html.twig`, `breadcrumb.html.twig`, `cards.html.twig` | templates | A list, a breadcrumb, cards |
