# Twig

```twig
<select name="status">
    {% for status in enum_cases('App\\OrderStatus', {group: 'customer'}) %}
        <option value="{{ status.value }}" title="{{ status|enum_description }}">{{ status|enum_label }}</option>
    {% endfor %}
</select>

{{ status|enum_meta('App\\Color') }}
{{ status|enum_badge }}
```

| Name | Kind | Returns |
|---|---|---|
| `enum_cases(enum, options = {})` | function | The cases of the enum as a `CaseCollection`, narrowed by `only`, `except`, `group` and `where_meta` — the options of the form field ([Forms](forms.md)). |
| `enum_label` | filter | The translated label when the enum is translatable and a translator is there; otherwise the case's `Label`, or its name. |
| `enum_description` | filter | The translated description in the same case; otherwise the case's `Description`; `null` without one. |
| `enum_meta(metaProperty)` | filter | The value of a meta property on the case, or its default value. |
| `enum_badge` | filter | The case as a badge: its label, with its `Color` and `Icon`, rendered by `@PhilEnums/badge.html.twig`. |

The functions and filters are registered as a Twig attribute extension and runtime; the runtime receives the
`translator` service when the application has one.

## Badges

`enum_badge` shows a case the way a status is shown in a list: its label — translated as `enum_label` translates it —
in the colour and with the icon the case carries. Both are meta properties the bundle ships:

```php
use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Application\Presentation\Icon;

enum OrderStatus: string
{
    #[Label('Pending')] #[Color('warning')] #[Icon('clock')]
    case PENDING = 'pending';

    #[Label('Delivered')] #[Color('success')] #[Icon('check')]
    case DELIVERED = 'delivered';
}
```

```html
<span class="enum-badge enum-badge--warning" data-enum-case="PENDING"><span class="enum-badge__icon" data-icon="clock" aria-hidden="true"></span>Pending</span>
```

- **A colour and an icon are names**, never CSS values or markup: `success`, `clock`. The application's stylesheet
  and icon set give them a look, so a theme changes without touching an enum.
- **The template is the application's to override**: copy it to `templates/bundles/PhilEnumsBundle/badge.html.twig`
  and write the markup of your design system — a Bootstrap badge, a Tailwind pill, a Twig component. It receives
  `case`, `label`, and `color` and `icon`, each `null` when the case carries none.
- **The output is HTML**: the template escapes what it prints, and the filter is marked safe.

The badge needs TwigBundle, which registers the bundle's `templates/` directory under `@PhilEnums`.

