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

## Messages and translations

The violations carry their numbers as integers — `{{ line }}` of each removal and `{{ count }}` of the removals past
the first 100 — and the other parameters as strings. The default messages are the translation keys, in the
`validators` domain.

The library ships catalogues in ICU MessageFormat, in the `validators+intl-icu` domain, in `translations/`:

| Locale | Messages |
|---|---|
| `en` | `moreMessage`, whose plural follows `{{ count }}`. |
| `fr` | Every default message: what was removed follows `{{ reason }}`, and the plural of `moreMessage` follows `{{ count }}`. |

```text
This CSS holds {{ count }} more parts that are not allowed.
→ {count, plural, one {This CSS holds # more part that is not allowed.} other {This CSS holds # more parts that are not allowed.}}
```

`{{ removed }}` is `RemovalReason::describe()`, in English: the French catalogue names each reason from
`{{ reason }}` instead, and falls back on `{{ removed }}` for a reason it does not know.

The catalogues are read once the application gives their directory to FrameworkBundle's translator
([Installation](installation.md#translations)); the ICU domain needs `MessageFormatter`, of `ext-intl` or of
`symfony/polyfill-intl-messageformatter`. Without them, the messages stay as written. The application's own
`translations/` directory comes after the library's: a message it translates there, in the `validators` or the
`validators+intl-icu` domain, replaces the library's. A message given to the constraint (`moreMessage: '…'`) is
translated as any key of the domain; written in ICU, it belongs in an ICU catalogue of the application.
