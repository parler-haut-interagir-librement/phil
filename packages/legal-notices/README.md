# phil/legal-notices

**The legal notice and the privacy policy of a site, as versioned documents in each locale.**

phil/legal-notices models the two documents a site publishes about itself: its legal notice — the imprint that says
who publishes the site, who directs the publication and who hosts it — and its privacy policy. A
`LegalDocumentVersion` is one version of one document: its number, its body in each locale and the format the body is
written in, the moment it takes effect, its publication state — draft, published, withdrawn — and a short summary of
what changed. A `DocumentHistory` holds every version of a document and finds the one in force at a moment, the next
one to take effect and the list the public sees.

A `SiteIdentity` holds the fields the documents name — the publisher, its address and registration, the director of
publication, the contact, the host, the privacy officer — and says which fields each document requires. A body names
those fields as placeholders, `${publisher_name}`, and also the retention periods, `${retention.R-001}`, and the
consent categories, `${consent.analytics}`, which the site gives through two ports. `LegalNotices` ties it together:
it reads the versions from a `DocumentSource`, renders a body through a `BodyRenderer`, fills its placeholders, lists
the versions published in a span of time and checks what each public document lacks.

It is for any PHP site that must publish a legal notice and a privacy policy, keep each version it published, and show
the periods and the cookies its privacy policy promises from the same data its code enforces. Every value is checked
where it is built — a locale, an e-mail address, a duration, a cookie name, the order of the dates of a version — and
every value filled into a body is filled into its text only, escaped for HTML, never into a tag. In a Symfony application, phil/legal-notices-bundle serves the
documents at `/legal` and `/privacy`, writes their versions in the configuration and reads the retention periods of
phil/privacy-bundle.

It is a plain PHP library with no dependency beyond the filter and mbstring extensions. Its ports are in `Contract`:
`DocumentSource`, `BodyRenderer`, `RetentionDurations` and `ConsentCategories`.

## Status

| Part | State |
|---|---|
| `DocumentType`, `Field`, `PublicationState`, `SiteIdentity` and the fields each document requires | Implemented and tested |
| `LegalDocumentVersion`, `DocumentHistory`: versions, the one in force, the next one, the public list | Implemented and tested |
| `RetentionDuration`, `RetentionKind`, `DurationWords`: periods in words in English and French | Implemented and tested |
| `ConsentCategory` | Implemented and tested |
| `Placeholders`, `PlainTextBody`, `LegalNotices`, `Problem`, `DocumentPublished` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The types, their rules, the versions and their states, the check. |
| [Placeholders](doc/placeholders.md) | The placeholders of a body, their values and how they are filled. |
| [Extension points](doc/extension-points.md) | The ports an application implements or replaces. |
| [Testing](doc/testing.md) | How the library is tested, and its fuzz targets. |

## Quick start

```php
$notices = new LegalNotices(
    $source,                        // a DocumentSource
    new PlainTextBody(),
    new SiteIdentity([
        'publisher_name' => 'Example Inc.',
        'contact_email' => 'hello@example.com',
        'dpo_name' => 'Jo Smith',
        'dpo_contact' => 'privacy@example.com',
    ]),
);

$version = $notices->inForce(DocumentType::PrivacyPolicy, new DateTimeImmutable());
$html = null === $version ? null : $notices->render($version, 'fr_CA');

foreach ($notices->check() as $problem) {
    echo $problem->code, ': ', $problem->message, "\n";
}
```

## Requirements

PHP 8.5 or later, with the filter and mbstring extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
