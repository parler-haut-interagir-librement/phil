# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-fileinfo` | any | A file's media type, read from its contents |
| `ext-gd` | any, with WebP and AVIF | Rendering images |
| `ext-mbstring` | any | Titles and file names, counted in characters |
| `ext-sodium` | any | Signatures in URL-safe base64 |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | Media, versions and collections; the `phil_media` column type |
| `league/flysystem` | ^3.30 | Files on a Flysystem filesystem: a local directory, an S3 bucket |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `symfony/console` | ^8.2 | `phil:media:placeholders` |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | The controller and its routes |
| `symfony/security-csrf` | ^8.2 | The stateless CSRF check of the explorer's forms |
| `symfony/security-core` | ^8.2 | The role or voter attribute the explorer asks for; Symfony's security, from SecurityBundle, must be there for the explorer to open |
| `symfony/process` | ^8.2 | `cwebp` and `avifenc`, run without a shell |
| `symfony/uid` | ^8.2 | Identifiers |
| `symfony/service-contracts` | ^3.6 | The profiler's trace, reset between requests |
| `psr/clock` | ^1.0 | Upload dates and the expiry of signed URLs; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `twig/twig` | optional | `media_url()`, `media_schema()`, the file explorer |
| `symfony/ux-twig-component` | optional | The `Img` and `Picture` components |
| `symfony/form` | optional | `MediaPickerType` |
| `symfony/validator` | optional | The `MediaFile` constraint |
| `symfony/asset-mapper` | optional | The Stimulus controllers, without a build step |
| `symfony/web-link` | optional | The main image of a page preloaded, with `priority` |
| `easycorp/easyadmin-bundle` | optional, ^5.8 | `MediaField`, `MediaAdmin` |
| `phil/browser-security-bundle` | optional | Every file served under its policy of user content |
| `phil/creation-disclosure` | optional | How a medium was made, as an IPTC digital source type |
| `phil/svg-sanitizer` | optional | SVG images purified and minified before they are kept, and served as images |
| libvips, `vips` and `vipsheader` | optional | Rendering with `processor: vips` or `auto` |

```sh
composer require phil/media-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\MediaBundle\PhilMediaBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_media.yaml
phil_media:
    storage_dir: '%kernel.project_dir%/var/media'   # where files are kept
    filesystem: ~                                   # or the service of a Flysystem filesystem, in place of the directory
    secret: '%kernel.secret%'                       # what URLs are signed with
    url_ttl: ~                                      # seconds a URL stays valid at least; ~, forever
    max_pixels: 25000000                            # the largest image, width times height, rendered to a format
    formats:
        thumbnail: { width: 320, height: 320 }                  # crop, quality 80
        hero:      { width: 1600, height: 900, mode: fit, quality: 70, widths: [480, 960] }
        card:      { transformers: [{ type: crop, width: 1200, height: 800 }, { type: widen, width: 960 }] }
    original:
        quality: 80                                 # the file itself converted to WebP, AVIF, JPEG or PNG
        widths: []                                  # and the narrower widths it is rendered at
    encoders:
        webp: auto                                  # cwebp when found on the PATH, else GD; "gd"; or a path
        avif: auto                                  # avifenc likewise
    processor: gd                                   # "vips", libvips required; "auto", libvips when found
    vips_binary: ~                                  # the path of vips; ~, searched on the PATH
    placeholder:
        enabled: false                              # a tiny preview of each version of a raster image; then phil:media:placeholders
        width: 16                                   # the box it fits in, 4 to 64 pixels
        quality: 40
    svg:
        purify: true                                # SVG images purified and served as images, with phil/svg-sanitizer
        precision: 3                                # decimals kept in coordinates
    delete_behaviour:
        default: restrict                           # what deleting a medium does to a record that shows it
        fields: { }                                 # 'App\Entity\Post.cover': set_null
    admin:
        layout: '@PhilMedia/admin/layout.html.twig'
        page_size: 48
        max_upload_bytes: 33554432
        attribute: ROLE_ADMIN
```

[Formats and URLs](formats.md) describes every transformation, [Storage](storage.md) the Flysystem filesystems.

The version of this bundle that adds the digital source type and the placeholder adds two nullable columns to
`phil_media`, `digital_source_type` and `placeholder`: a Doctrine migration adds them to an existing table.

```yaml
# config/routes/phil_media.yaml
phil_media:
    resource: '@PhilMediaBundle/config/routes.php'
    prefix: /media

# config/routes/phil_media_admin.yaml — the file explorer, behind the application's firewall and admin.attribute
phil_media_admin:
    resource: '@PhilMediaBundle/config/admin_routes.php'
    prefix: /admin/media
```

Each optional part is wired when what it needs is installed: the `phil_media` column type with DoctrineBundle, the
picker's form theme with Twig and the form component, the Stimulus controllers with AssetMapper, the components with
`symfony/ux-twig-component`, the preload of the main image with `symfony/web-link`, the EasyAdmin field and menu link
with EasyAdmin, the profiler panel in debug.

With DoctrineBundle, the bundle maps its entities itself. The application's migrations create three tables:

| Table | Holds |
|---|---|
| `phil_media` | A medium: its current file, description, license, AI disclosure and focal point |
| `phil_media_version` | The files a medium had before, one row per replacement, deleted with it |
| `phil_media_collection` | Collections, by a unique key |

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A collection is found by its key and a file by its
storage key, each as it was written: the collection `Press` is never found for `press`, and the file stored under
`2026/Photo.jpg` is never taken for `2026/photo.jpg`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_media` | `id`, `collection`, `storage_key` |
| `phil_media_version` | `id`, `media`, `storage_key` |
| `phil_media_collection` | `id`, `collection_key` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes
the migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. On
PostgreSQL and SQLite the diff is empty.

The join columns `collection` and `media` are converted with the identifiers they reference, and MySQL refuses to change one side of
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
SELECT storage_key, COUNT(*) AS row_count FROM phil_media
GROUP BY storage_key HAVING COUNT(DISTINCT CAST(storage_key AS BINARY)) > 1;
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.

A `MediaStorage` or `ImageProcessor` service of the application's own, aliased, replaces the directory or GD.
