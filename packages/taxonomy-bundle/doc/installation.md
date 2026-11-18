# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl`, `ext-mbstring` | any | The request's language; names counted in characters |
| `doctrine/orm` | ^3.7 | The tables |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The form types |
| `phil/slug` | ^0.1 | The slugs of tag names and category keys |
| `phil/resource` | ^0.1 | `ResourceReference`, the resource a tag or a category is given to |
| `symfony/uid` | ^8.2 | Identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |

```sh
composer require phil/taxonomy-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\TaxonomyBundle\PhilTaxonomyBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_taxonomy.yaml
phil_taxonomy:
    fallback_locale: en   # the language of a name when there is none in the language asked
    slugger: ~            # the service id of the slugger of names and keys; ~ for the default one
```

With DoctrineBundle, the bundle maps its entities; the application's migrations create four tables:

| Table | Columns |
|---|---|
| `phil_taxonomy_tag` | `id`, `name` (100), `slug` (128, unique) |
| `phil_taxonomy_category` | `id`, `category_key` (128, unique), `parent` (restricted on delete), `path` (420, indexed), `position` |
| `phil_taxonomy_category_translation` | `id`, `category` (cascades on delete), `locale` (16), `name` (150), `description`; unique on category and locale |
| `phil_taxonomy_assignment` | `id`, `term_type` (`tag` or `category`), `term_id`, `resource_kind` (64), `resource_id` (255); unique on all four, indexed on the resource |

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A tag, a category or a resource is found by its
key as it was written: the tag `Doc-A` is never found for `doc-a`, and both slugs can be taken; the terms of the
resource `Doc-A` are never listed for `doc-a`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_taxonomy_tag` | `id`, `slug` |
| `phil_taxonomy_category` | `id`, `category_key`, `parent`, `path` |
| `phil_taxonomy_category_translation` | `id`, `category`, `locale` |
| `phil_taxonomy_assignment` | `id`, `term_type`, `term_id`, `resource_kind`, `resource_id` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes
the migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. On
PostgreSQL and SQLite the diff is empty.

The join columns `parent` and `category` are converted with the identifiers they reference, and MySQL refuses to change one side of
a foreign key before the other. Put `SET FOREIGN_KEY_CHECKS = 0` at the start of the migration's `up()` and
`SET FOREIGN_KEY_CHECKS = 1` at its end; the foreign keys stay, and are checked again once both sides are converted:

```php
public function up(Schema $schema): void
{
    $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
    // The statements doctrine:migrations:diff wrote.
    $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
}
```

The change only makes values more distinct, so no existing row breaks a unique key. It parts what the
case-insensitive collation joined: rows whose keys differ only by case, which a lookup by either spelling returned
together, are each found by their own spelling from the migration on, and a key the application looks up in
another case than it wrote stops matching. Before migrating, list the keys written in more than one case:

```sql
SELECT resource_kind, resource_id, COUNT(*) AS row_count FROM phil_taxonomy_assignment
GROUP BY resource_kind, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.
