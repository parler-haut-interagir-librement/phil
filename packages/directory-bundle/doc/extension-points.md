# Extension points

What an application configures, overrides, decides or relies on. Listings are contents of `phil/content-bundle`, and
addresses, contact cards and positions come through `phil/address-bundle`, `phil/contact-bundle` and
`phil/geo-bundle`.

## Configuration

| Option | What it extends |
|---|---|
| `genres` | The kinds of listing, each with its path and title |
| `relations` | How listings of one genre point at another's, one or many, with a title each way |
| `admin_role` | The role that publishes, changes every listing and names editors |
| `publish_every_change` | Whether a change is published at once or kept as a draft |
| `page_size`, `sitemap_locale` | The list of a genre, and the locale the sitemap lists |
| `words` | The words of the addresses, `new`, `edit`, in the site's language |

See [Configuration](configuration.md).

## Access

The voter attributes `phil_directory.edit`, `phil_directory.editors` and `phil_directory.publish` decide who changes,
manages and publishes a listing; a voter of the application's own on the same attributes adds its rules. Users are
read from the security token's `getUserIdentifier()`. See [Listings](listings.md#who-may-do-what).

## Templates and words

| Point | How |
|---|---|
| The pages | `@PhilDirectory/index`, `show`, `form`, `editors` and `history`, each extending `layout.html.twig`, overridden in `templates/bundles/PhilDirectoryBundle/` — see [Routes and pages](routes.md#templates) |
| Showing a name rather than an identifier | The `editors` and `history` templates overridden |
| Labels | The `phil_directory` translation domain |
| The forms | A form type extension of the application's on the listing and editor forms |

## Ports

| Port | Shipped | What the application's own does |
|---|---|---|
| `Phil\DirectoryBundle\Application\UserErasure` | `DoctrineUserErasure` | `erase(string $user): int` — takes a user's identifier off every listing and version, in one transaction; returns how many changed |
| `Phil\DirectoryBundle\Application\RelatedLookup` | `DoctrineRelatedLookup` | `pointingAt(string $genre, string $relation, string $resourceId): array` — the listings of a genre whose relation points at a resource |

## Services

| Point | How |
|---|---|
| `Directory` | Creates, edits, names editors, publishes and erases users in code |
| The sitemap | `DirectoryUrlProvider` is tagged `phil_sitemap.url_provider`: one section per genre |
| The capabilities of a listing | `ContactCapable`, `AddressCapable`, `LocationCapable`, `RelatedCapable` and `EditedByCapable`, mapped for `phil/content-bundle` through `phil_content.capability_mapping` |
