# phil/directory-bundle

**A directory of listings, such as podcasts or shops, that members write and administrators publish.**

phil/directory-bundle keeps listings sorted into genres, such as podcasts, local shops or larp sites, in a single
table. Adding a genre takes a line of configuration and no class. Each listing is a content of phil/content-bundle,
so every change is a version and the history is kept, page by page. Besides content-bundle's title, description,
slug, body and SEO, a listing carries a contact card, an address, a map location, the listings it names and who
edited each version. The bundle gives each genre its routes, forms and pages, and a section of the sitemap. Its author
decides who finds a listing, as phil/indexing-consent names it: everyone, the visitors of the site but not search
engines, or only those who have its address.

It is for a community site where people list what they know. Anyone signed in writes a listing and becomes its
author. The author, the editors the author names and the administrators change it, and an administrator publishes
it, unless `publish_every_change` publishes every change at once. Genres relate as the configuration says (a larp is
played at larp sites), and a listing shows both the listings it names and those that name it. For pages that only
editors write, phil/content-bundle alone is enough.

It is a Symfony bundle built on phil/content-bundle, with Doctrine, Symfony Form and a Symfony Security voter for the
`phil_directory.edit`, `phil_directory.editors` and `phil_directory.publish` attributes. It adds its own capabilities
to content-bundle's pipeline, and takes contact cards, addresses and coordinates from phil/contact-bundle,
phil/address-bundle and phil/geo-bundle.

## Status

| Part | State |
|---|---|
| `Listing` and `ListingDimension`: one table, genres, versions | Implemented and tested |
| The capabilities contact, address, location, related and edited-by | Implemented and tested |
| `Directory`: create, edit, publish, editors, history | Implemented and tested |
| Relations between genres, declared in the configuration, shown on both listings | Implemented and tested |
| The forms, the controller, the routes of each genre | Implemented and tested |
| The voter `phil_directory.edit`, `.editors`, `.publish` | Implemented and tested |
| The sitemap: one section per genre | Implemented and tested |
| Who finds a listing: listed, kept out of search engines, unlisted (phil/indexing-consent) | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, the security the bundle reads. |
| [Configuration](doc/configuration.md) | Genres, the administrator role, publishing, the words of the addresses. |
| [Listings](doc/listings.md) | Writing, editing, editors, publishing, history, who may do what, who finds a listing, and erasing a user. |
| [Routes and pages](doc/routes.md) | The routes of each genre, the templates, the translations, the sitemap. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/directory-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\DirectoryBundle\PhilDirectoryBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_directory.yaml
phil_directory:
    genres:
        podcast: { path: podcasts, title: Podcasts }
```

```yaml
# config/routes/phil_directory.yaml
phil_directory:
    resource: .
    type: phil_directory
```

## Requirements

PHP 8.5 or later; `phil/content-bundle`, `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` and
`phil/ddd-bundle` 0.1; `phil/indexing-consent` 0.1; Doctrine ORM 3.7; Symfony 8.2 (form, routing, security-core);
Twig 3.21. `phil/sitemap` lists the pages when `phil/sitemap-bundle` is installed.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
