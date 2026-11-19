# phil/short-link-bundle

**Short links for Symfony on dedicated domains: codes in the Sqids format, validity dates, a maximum number of visits,
a QR code for each link, and visits of people counted apart from robots and link previews, without any visitor data.**

phil/short-link-bundle answers a short domain of its own, such as `go.example.com`, with one domain per locale when a
site needs it. Each link has a code — drawn at random and written in the Sqids format, or chosen —, a target URL, a
redirect status, and optional terms: a start, an end, a maximum number of visits. A visit is counted in the same
statement that checks the terms, so that two requests racing for the last visit count it once. Robots, prefetches and
the link previews of messaging services are redirected but counted apart: they never use up a maximum. A link no
longer in force answers `410 Gone`; a code nobody holds answers `404`. The location is written by the resolver of
phil/redirects-bundle, so a short link goes where a redirect would and is checked the same way.

Targets are absolute URLs read by phil/url, limited to the hosts the application lists, never on a short link domain,
and optionally read through phil/safe-http before saving. Creating links is rate limited per author. Nothing of the
visitor is stored: a link keeps its counts and the moment of its last visit — the `User-Agent` is read to classify a
visit, then dropped —, and the recorders told of a visit — the
server events of phil/analytics-bundle among them — receive identifiers only.

## Status

| Part | State |
|---|---|
| Codes in the Sqids format, alphabet and blocklist configurable | Implemented, tested and fuzzed |
| Links, their terms and their Doctrine repository, on SQLite, MySQL and PostgreSQL | Implemented and tested |
| Short link domains answered before the routing, `404` and `410` | Implemented and tested |
| Atomic count of the visits, racing requests included | Implemented and tested on MySQL and PostgreSQL |
| Target checks, rate limit on creation | Implemented and tested |
| The administration, its form, its QR codes in SVG and PNG | Implemented and tested |
| Visits of people and of machines counted apart, the classifier replaceable | Implemented, tested and fuzzed |
| Visit recorders and the analytics recorder | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the table, the routes, the DNS of a short domain. |
| [Configuration](doc/configuration.md) | Every option of `phil_short_link`. |
| [Codes](doc/codes.md) | The Sqids format, the alphabet, the blocklist, drawn and chosen codes. |
| [Resolution](doc/resolution.md) | What a request on a short domain answers, the domains of the locales, the count of visits. |
| [Safety and privacy](doc/safety.md) | Targets, open redirects, the rate limit, what is kept and what is not. |
| [Administration](doc/administration.md) | The pages, the role, the CSRF token, the layout, the QR codes. |
| [Extension points](doc/extension-points.md) | Recorders, checks, repositories and services an application chooses or replaces. |
| [Testing](doc/testing.md) | The tests, the servers they read and the fuzz target. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/short-link-bundle
```

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    domains:
        - { host: go.example.com }
        - { host: lien.example.fr, locale: fr }
    home_url: 'https://www.example.com/'
    targets:
        allowed_hosts: [www.example.com, www.example.fr]
```

```yaml
# config/routes/phil_short_link.yaml
phil_short_link_admin:
    resource: '@PhilShortLinkBundle/config/routes/admin.php'
    prefix: /admin/short-links
```

```php
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Application\ShortLinks;

$draft = new ShortLinkDraft();
$draft->target = 'https://www.example.com/festival/2027';
$draft->maxVisits = 500;

$link = $shortLinks->create($draft, 'user:'.$user->getUserIdentifier());
$shortLinks->url($link); // https://go.example.com/Xk3mQp
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5 or later, and `phil/url` 0.1.3, which requires
the intl, mbstring and URI extensions. The administration needs Twig, the forms, the translator, the security and its
CSRF tokens; the check of a target needs phil/safe-http; the analytics recorder needs phil/analytics-bundle.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
