# phil/email-preview

**Catch what breaks an email before it is sent: a missing text part, no one-click unsubscribe, tracking pixels,
leftover styles, too wide or long.**

phil/email-preview is a plain PHP library. It checks an email without sending it — each preview of a template rendered
by phil/email-templates-bundle, or any email built for Symfony Mailer — against a set of rules, and gives a report to
show beside the preview, as text, as an HTML fragment or as JSON.

The HTML is read by the HTML5 parser of PHP (`Dom\HTMLDocument`), as a browser reads it, whatever its shape.

## The default rules

| Rule | Checks | Result |
|---|---|---|
| `text-part` | The email has a plain-text part, and it gives the address of every `http:`, `https:` and `mailto:` link of the HTML. | Error |
| `one-click-unsubscribe` | An email sent in bulk has `List-Unsubscribe` with an `https:` address and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058). | Error |
| `tracking-pixel` | No remote image of one pixel or less, by its attributes or its style, and no image address that carries a value of the recipient. | Error |
| `no-style-element` | No `<style>` element remains: the styles are inlined. | Error |
| `max-width` | No element is wider than 600 pixels, by its `width` attribute or the `width`, `min-width` or `max-width` of its style. | Error |
| `subject-length` | The subject holds at most 50 characters, counted as a reader sees them. | Warning |

An application adds its own rules, or replaces these, through the `Rule` interface.

## Status

| Part | State |
|---|---|
| The six default rules and the `Rule` interface | Implemented |
| The report, as text, as an escaped HTML fragment and as JSON | Implemented |
| The check of each preview of a template of phil/email-templates-bundle | Implemented |
| The check of a `Symfony\Component\Mime\Email` | Implemented |

The preview itself — the email drawn in a sandboxed frame — belongs to the administration of
phil/email-templates-bundle; this library adds the check beside it.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, checking an email and showing the report. |
| [Reference](doc/reference.md) | Every public type, with its role. |
| [Extension points](doc/extension-points.md) | Writing a rule, and what is stable when. |
| [Testing](doc/testing.md) | The package's tests and its benchmark. |

## Quick start

```sh
composer require phil/email-preview
```

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;

$report = new EmailChecker()->check(CheckedEmail::fromRendered($renderedEmail, Sending::bulk($headers)));

if (!$report->passes()) {
    echo $report->toText();
}
```

Every preview of a template, each checked:

```php
use Phil\EmailPreview\Preview\PreviewChecks;

foreach (new PreviewChecks($previewRenderer)->check($key, $site, $locale) as $preview) {
    echo $preview->report->toHtml();
}
```

## Requirements

PHP 8.5 or later, with the `dom`, `intl`, `libxml` and `mbstring` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
