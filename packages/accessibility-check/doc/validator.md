# Validator

`Validator\AccessibleContent` is a constraint of `symfony/validator`: the value, HTML in a string or a `Stringable`,
or a UJO `Document`, is accessible. `null` and `''` are valid, as for every constraint but `NotBlank`; any other value
throws `UnexpectedValueException`.

```php
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\Validator\AccessibleContent;

final class ArticleInput
{
    #[AccessibleContent]
    public ?string $body = null;

    #[AccessibleContent(minSeverity: Severity::Warning, language: 'fr', locale: 'fr')]
    public ?Document $content = null;
}
```

| Option | Default | Means |
|---|---|---|
| `minSeverity` | `Severity::Error` | the findings that are violations: this severity and graver |
| `language` | `null` | the language of a content that does not say its own; the policy's when `null` |
| `locale` | `null` | the locale of the messages; the validator's when `null` |
| `message` | `{{ message }}` | the message of a violation |
| `refusedMessage` | `This content cannot be checked for accessibility: {{ reason }}` | the message of a refused content |

Each finding is a violation of code `AccessibleContent::INACCESSIBLE_ERROR`, with the parameters `{{ message }}` —
the finding's message —, `{{ rule }}`, `{{ criterion }}`, `{{ severity }}` and `{{ location }}`. A content the
checker refuses — too large, not UTF-8 — is one violation of code `REFUSED_ERROR` with `{{ reason }}`.

`AccessibleContentValidator` takes the `AccessibilityChecker` it checks with, and the locale of the messages: register
it as a service, tagged `validator.constraint_validator`, for the constraint to follow the application's policy
([Installation](installation.md#in-a-symfony-application)). Without that declaration, the validator is built with the
default policy and writes in English.
