# Installation

```sh
composer require phil/email-preview
```

The package needs PHP 8.5 or later, with the `dom`, `intl`, `libxml` and `mbstring` extensions. It requires
phil/email-templates-bundle, whose previews it checks, and symfony/mime, whose emails it also reads.

It is a plain library: nothing to register. An application that wants one checker for every request builds it once —
as a service, in a Symfony application — since the checker and its rules keep no state between two emails:

```yaml
# config/services.yaml
services:
    Phil\EmailPreview\EmailChecker: ~
    Phil\EmailPreview\Preview\PreviewChecks:
        arguments:
            $renderer: '@Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer'
```

## Checking an email

An email is given to the rules as a `CheckedEmail`: its subject, its HTML, its plain-text part and a `Sending`, which
says what the rendering does not — whether it goes out in bulk, the headers the sender adds, and the values that
identify the recipient.

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;

$sending = Sending::bulk(
    ['List-Unsubscribe' => '<https://example.com/leave/abc>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
    ['ana@example.com', 'subscriber-4242'],
);

$report = new EmailChecker()->check(CheckedEmail::fromRendered($renderedEmail, $sending));
```

`CheckedEmail::fromMime($email, bulk: true)` reads an email built for Symfony Mailer: its headers are those of the
sending, and its recipients' addresses are the values that identify them.

An HTML larger than `CheckedEmail::MAX_HTML_BYTES` (2 MiB) is refused with `EmailTooLarge` before it is parsed.

## Showing the report

`Report::toText()` writes one finding a line; `Report::toHtml()` writes an HTML fragment, every value escaped, to place
beside the preview; `json_encode($report)` gives it to a script. `Report::passes()` is true without error, and
`passes(strict: true)` without any finding.
