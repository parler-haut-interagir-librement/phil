# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a check | Implement `Phil\EmailPreview\Contract\Rule` and give it to `EmailChecker` with the default rules. |
| Change a limit | Give `new MaxWidth(480)` or `new SubjectLength(70)` in place of the default one. |
| Leave a rule out | Give `EmailChecker` the rules you keep. |
| Check the previews with data of your own | Give `PreviewChecks` your own `PreviewRenderer` of phil/email-templates-bundle. |

## Writing a rule

A rule has a name in kebab case, which each of its findings carries, and yields what it finds in the order of the
email. It reads the HTML through `CheckedEmail::$document`, parsed once for every rule, never with regular expressions
on the HTML. It keeps no state between two emails: one checker serves every request of a worker.

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

final readonly class ImageAlt implements Rule
{
    public function name(): string
    {
        return 'image-alt';
    }

    public function check(CheckedEmail $email): iterable
    {
        foreach ($email->document->getElementsByTagName('img') as $image) {
            if (!$image->hasAttribute('alt')) {
                yield Finding::error('image-alt', 'An image has no alternative text.', $image->getAttribute('src'));
            }
        }
    }
}
```

The checker runs the rules in the order it is given them, which is the order of the report:

```php
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Rule\DefaultRules;

$checker = new EmailChecker([...DefaultRules::all(), new ImageAlt()]);
```

In a Symfony application, tag the rules and give the checker the tagged iterator:

```yaml
services:
    _instanceof:
        Phil\EmailPreview\Contract\Rule:
            tags: ['app.email_rule']

    Phil\EmailPreview\EmailChecker:
        arguments:
            $rules: !tagged_iterator app.email_rule
```

A rule that needs what the rendering does not say — a header, whether the email goes out in bulk, the values of the
recipient — reads it from `CheckedEmail::$sending`.
