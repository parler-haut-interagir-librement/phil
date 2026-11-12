# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/content-bundle` | ^0.1@dev | Listings are contents: versions, workflow, routes |
| `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` | ^0.1@dev | The contact card, the address and the position of a listing, their columns and form types |
| `phil/ddd-bundle` | ^0.1@dev | The identifier of a listing and its Doctrine type |
| `phil/sitemap` | ^0.1@dev | The sitemap provider, collected when `phil/sitemap-bundle` is installed |
| `phil/indexing-consent` | ^0.1@dev | Who finds a listing, as its author decides |
| `doctrine/orm` | ^3.7 | The two tables |
| `symfony/form`, `symfony/routing`, `symfony/http-kernel` | ^8.2 | The forms, the routes and the controller |
| `symfony/security-core` | ^8.2 | Who is signed in, and the voter |
| `twig/twig` | ^3.21 | The pages |

```sh
composer require phil/directory-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
    Phil\ContactBundle\PhilContactBundle::class => ['all' => true],
    Phil\AddressBundle\PhilAddressBundle::class => ['all' => true],
    Phil\GeoBundle\PhilGeoBundle::class => ['all' => true],
    Phil\DirectoryBundle\PhilDirectoryBundle::class => ['all' => true],
];
```

## What the bundle prepends

When the extension is registered, the bundle prepends:

- to `doctrine`: the type `phil_directory_listing_id` and the attribute mapping `PhilDirectory` of `src/Domain/Model`;
- to `phil_content`: `Listing` as a content root, under the resource key `listing`.

The application creates the tables with its own migrations. The listing's table holds who finds it in two columns,
`indexing` (`listed` by default) and `site_listed` (true by default), so that a migration adds them to existing rows
without changing what they show.

## Security

The bundle reads the services `security.token_storage` and `security.authorization_checker`, which SecurityBundle
provides. The user is the identifier of the token — `getUserIdentifier()` — and an administrator is a user granted the
role `admin_role`, through the role hierarchy when there is one. Nobody signed in reads the published listings and
nothing else.
