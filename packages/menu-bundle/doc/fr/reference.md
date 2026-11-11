<!-- phil-dev source-sha256=dfade58383c69f406c2c7e9a0b373e9b3b31def779943b617c24059f67b28cd7 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilMenuBundle` | `final class`, `AbstractBundle` | Alias `phil_menu` ; configuration automatique des constructeurs et des décorateurs |
| `Domain\MenuItem` | `final readonly class` | Une entrée d'un menu |
| `Domain\Menu` | `final readonly class` | Un menu nommé, son fil d'Ariane |
| `Domain\InvalidMenu` | `final class`, `InvalidArgumentException` | Un élément ou un menu impossible |
| `Application\MenuBuilder` | interface | Fabrique un menu |
| `Application\MenuDecorator` | interface | Modifie les éléments des menus |
| `Application\MenuContext` | `final readonly class` | L'endroit où se trouve le visiteur |
| `Application\Menus` | `final readonly class` | Construit, décore et marque les menus |
| `Application\BreadcrumbSchema` | `final class` | Un fil d'Ariane sous forme de `BreadcrumbList` de schema.org, ses noms et ses URL étant donnés |
| `Infrastructure\Twig\MenuExtension` | extension Twig | `menu`, `menu_breadcrumb`, `menu_breadcrumb_schema` |
| `templates/menu.html.twig`, `breadcrumb.html.twig`, `cards.html.twig` | gabarits | Une liste, un fil d'Ariane, des cartes |
