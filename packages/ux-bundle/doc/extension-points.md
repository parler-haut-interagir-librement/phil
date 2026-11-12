# Extension points

What an application configures, overrides or relies on. The bundle declares no PHP port and registers no service: its
contract is its configuration key, the blocks of its form theme and the logical path of the stylesheet.

## The form theme

| Point | How |
|---|---|
| Turning it off | `phil_ux.form_theme: false` ([The form theme](form-theme.md#applied-to-every-form)) |
| A block changed | A theme of the application listed in `twig.form_themes`, which comes after the bundle's and wins for the blocks it writes |
| One form, another theme | `{% form_theme form 'theme.html.twig' %}` in its template, or `with [...] only` to leave out every global theme |
| Classes | `row_attr`, `label_attr`, `help_attr` and `attr` of a field: their classes are added after the theme's |
| The look | The classes `isField`, `isFieldSet`, `isFieldLabel`, `isFieldLegend`, `isFieldDescription`, `isFieldError` and `isRadioGroup`, dressed by phil/ux-css, written again in the application's styles outside any layer |

## The stylesheet

| Point | How |
|---|---|
| The logical path | `phil-ux/phil-ux.css`, `PhilUxBundle::STYLESHEET`, for `asset()` or an entry of the importmap |
| The directory | `PhilUxBundle::stylesheetDirectory()`, the `dist/` directory of phil/ux-css where Composer installed it |
| The look | The custom properties `--phil-*` and the cascade layers of phil/ux-css, described in its documentation |
