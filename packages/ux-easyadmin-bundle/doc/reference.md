# Reference

| Type or file | Kind | Role |
|---|---|---|
| `PhilUxEasyAdminBundle` | `final class`, `AbstractBundle` | Alias `phil_ux_easy_admin`; the key `theme`; `assets/styles` mapped as `phil-ux-easyadmin/`; the listener |
| `PhilUxEasyAdminBundle::STYLESHEET` | constant | `phil-ux-easyadmin/phil-ux-easyadmin.css` |
| `PhilUxEasyAdminBundle::STYLES_DIR` | constant | The directory AssetMapper maps |
| `Infrastructure\EasyAdmin\ThemeStylesheet` | `final readonly class`, `kernel.request` listener | Adds the stylesheet to the CSS assets of the request's EasyAdmin context |
| `Infrastructure\Stylesheet\ThemeSheet` | `final readonly class` | `build()` writes the single stylesheet from its source and the token files; `tokensDirectory()` finds those of phil/ux-css |
| `assets/src/phil-ux-easyadmin.css` | stylesheet source | The layer order, the imports of the token files, the layer `phil-easyadmin` |
| `assets/styles/phil-ux-easyadmin.css` | stylesheet | The file served, written by `bin/build.php` ([The theme](theme.md)) |
| `bin/build.php` | script | Writes `assets/styles/phil-ux-easyadmin.css` |
| `recipe/0.1/` | Flex recipe | Registers the bundle and writes `config/packages/phil_ux_easy_admin.yaml` |

| Configuration key | Default | Meaning |
|---|---|---|
| `phil_ux_easy_admin.theme` | `true` | Whether every page of every dashboard links the stylesheet |

| Service | Class |
|---|---|
| `phil_ux_easy_admin.theme_stylesheet` | `ThemeStylesheet`, with `theme: true` |
