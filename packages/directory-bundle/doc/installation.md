# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/content-bundle` | ^0.1@dev | Listings are contents: versions, workflow, routes |
| `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` | ^0.1@dev | The contact card, the address and the position of a listing, their columns and form types |
| `phil/ddd-bundle` | ^0.1@dev | The identifier of a listing and its Doctrine type |
| `phil/sitemap` | ^0.1@dev | The sitemap provider, collected when `phil/sitemap-bundle` is installed |
| `phil/indexing-consent` | ^0.1@dev | Who finds a listing, as its author decides |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | The two tables; the platform of the connection, which `BinaryKeysListener` reads |
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

### Collation of the keys

**The genre, the author and who wrote a version compare byte by byte**, as PHP compares them. A user identifier may
mix cases: a query on `author` for `Ann` — an application's list of a user's listings — never returns those of `ann`,
and erasing `Ann` never empties `edited_by` on the versions `ann` wrote. The bundle writes
genres in lowercase ASCII; a row written outside it in the genre `Podcast` or `pódcast` is never listed under
`podcast`. `BinaryKeysListener` gives these columns the binary collation when the ORM loads the metadata of `Listing`,
and of every class that implements `EditedByCapable`, on MySQL or MariaDB; the mapping itself stays the same on every
platform. `edited_by` is mapped by the mapping listener of content-bundle on the same event: the bundle registers
`BinaryKeysListener` after it, with a lower priority.
The keys of the rows of a listing — locale and stage — are those of content-bundle, which gives them the same
collation.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_directory_listing` | `genre`, `author` |
| `phil_directory_listing_dimension` | `edited_by` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings `genre`, `author` and `edited_by` to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`.
By hand, per platform:

```sql
-- MySQL and MariaDB
ALTER TABLE phil_directory_listing
    MODIFY genre VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
    MODIFY author VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
ALTER TABLE phil_directory_listing_dimension
    MODIFY edited_by VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
-- PostgreSQL: nothing to run, the default collation is deterministic.
-- SQLite: nothing to run, the columns compare bytes.
```

The change only makes values more distinct; none of these columns is part of a unique key. It parts what the case-insensitive
collation joined: a genre or an author that a lookup in another case found stops matching from the migration on.
Before migrating, list the authors and the writers of versions written in more than one case:

```sql
SELECT author, COUNT(*) AS row_count FROM phil_directory_listing
GROUP BY author HAVING COUNT(DISTINCT CAST(author AS BINARY)) > 1;
SELECT edited_by, COUNT(*) AS row_count FROM phil_directory_listing_dimension
GROUP BY edited_by HAVING COUNT(DISTINCT CAST(edited_by AS BINARY)) > 1;
```

Each line is one user under several spellings. Keep them apart when they are two users, or bring the rows to the
spelling of the user identifier with an `UPDATE` before the migration.

## Security

The bundle reads the services `security.token_storage` and `security.authorization_checker`, which SecurityBundle
provides. The user is the identifier of the token — `getUserIdentifier()` — and an administrator is a user granted the
role `admin_role`, through the role hierarchy when there is one. Nobody signed in reads the published listings and
nothing else.
