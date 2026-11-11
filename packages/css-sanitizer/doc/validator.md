# Validator

`Validator\SafeCss` is a constraint for `symfony/validator`: the value, a string or a `Stringable`, is CSS the
sanitizer keeps whole. Each removal listed is a violation, and the removals past the first 100 are one more. `null`
and the empty string are valid.

```php
use Phil\CssSanitizer\Validator\SafeCss;

final class Theme
{
    #[SafeCss]
    public string $styleSheet = '';

    #[SafeCss(declarations: true)]
    public string $titleStyle = '';
}
```

| Option | Default | Meaning |
|---|---|---|
| `declarations` | `false` | Check the content of a `style` attribute rather than a style sheet |
| `message` | `This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.` | The message of each violation |
| `moreMessage` | `This CSS holds {{ count }} more parts that are not allowed.` | The message of the removals past the first 100 |

The parameters of each violation are `{{ removed }}` (`RemovalReason::describe()`), `{{ subject }}`, `{{ line }}` and
`{{ reason }}` (the reason's value, such as `import`). The violation of the removals past the first 100 has
`{{ count }}` as its only parameter and the same count as its plural: a source of a megabyte gives at most 101
violations. The code is `SafeCss::NOT_ALLOWED_ERROR`.

The policy is the one of the `CssSanitizer` given to `SafeCssValidator`. Without a service declaration, the validator's
factory builds it with the default policy, which refuses every URL; declare it as a service to use the application's
policy, as [Installation](installation.md) shows.

A form can validate with `SafeCss` and store the sanitized CSS, or skip the constraint and store what `sanitize()`
returns, telling the author what was removed.
