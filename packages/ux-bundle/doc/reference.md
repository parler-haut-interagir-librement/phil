# Reference

| Type or file | Kind | Role |
|---|---|---|
| `PhilUxBundle` | `final class`, `AbstractBundle` | Alias `phil_ux`; the key `form_theme`; the form theme prepended to `twig.form_themes`; the stylesheet's directory mapped in AssetMapper |
| `PhilUxBundle::FORM_THEME` | constant | `@PhilUx/form/phil_ux_layout.html.twig` |
| `PhilUxBundle::STYLESHEET` | constant | `phil-ux/phil-ux.css`, the logical path of the stylesheet |
| `PhilUxBundle::stylesheetDirectory()` | static method | The `dist/` directory of phil/ux-css |
| `templates/form/phil_ux_layout.html.twig` | form theme | The blocks `form_row_render`, `form_help`, `form_errors` and `choice_widget_expanded` ([The form theme](form-theme.md)) |

| Configuration key | Default | Meaning |
|---|---|---|
| `phil_ux.form_theme` | `true` | Whether the form theme is prepended to `twig.form_themes` |

The bundle registers no service.
