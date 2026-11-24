# phil/newsletter-archive

**The public archive of a site's sent newsletters: the issues an editor publishes, each at a clean address or behind a
revocable link, in a web version that holds nothing of a subscriber.**

phil/newsletter-archive is a Symfony bundle on `phil/newsletter-bundle`. A sent campaign enters the archive only when
an editor publishes it: it is then listed, a page at a time, filtered by list and by tag, and read at a slug made from
its subject. An editor may also share a campaign by a link, published or not: an opaque token of which the archive
keeps only the hash, revoked in one call, replaced by sharing again. Unpublishing withdraws both.

The web version is rendered once, when the issue is published, and kept as it is. It is rendered with neutral data in
place of the subscriber's: no unsubscription or preference link, no signed token, no tracked link, no tracking pixel,
nothing an inline style hides; then it is purified by `phil/markup`. Since the archive keeps no personal data, erasing
a subscriber leaves it as it is.

The pages are plain Twig templates, without a class the page needs, read well by the phil-ux stylesheet and overridden
like any bundle template; an issue's page gives its canonical address and its Open Graph title and description. A
command publishes, unpublishes, shares, revokes and retags; `IssueFeed` gives the latest issues to a feed.

## Status

| Part | State |
|---|---|
| `ArchivedIssue`, `Tag`, `ArchiveFilter`, `IssuePage`, `ArchiveRepository` | Implemented and tested |
| `Archive`, `ArchiveReader`, `NeutralWebVersion`, `LinkTokens`, `IssueFeed` | Implemented and tested |
| `DbalArchiveRepository`, `ArchiveSchema`, `ArchiveSchemaListener` | Implemented and tested on SQLite |
| The pages, their routes and templates, the command, the Flex recipe | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the routes and the tables. |
| [Configuration](doc/configuration.md) | Every option of `phil_newsletter_archive`. |
| [The archive](doc/archive.md) | Publishing, unpublishing, sharing by a link, tags and slugs, from code and from the command line. |
| [The web version](doc/web-version.md) | How an issue is rendered with neutral data, purified and frozen; what that means for privacy. |
| [Pages](doc/pages.md) | The routes, the templates and their variables, the canonical address, the headers of a shared page. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/newsletter-archive
```

```yaml
# config/routes/phil_newsletter_archive.yaml
phil_newsletter_archive:
    resource: '@PhilNewsletterArchiveBundle/config/routes.php'
    prefix: /newsletter/archive
```

```sh
bin/console phil:newsletter-archive publish <campaign-id> --tag=events
bin/console phil:newsletter-archive share <campaign-id>
```

## Requirements

PHP 8.5 or later, `phil/newsletter-bundle` 0.3 or later, Doctrine DBAL 4.5, Symfony 8.2 and Twig 3.30.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
