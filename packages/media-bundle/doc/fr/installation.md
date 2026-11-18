<!-- phil-dev source-sha256=11a57f6e62a617a07f800dd03ea4e55d211aa96ca658761ff07ade97febaca81 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-fileinfo` | toute | Le type de média d'un fichier, lu dans son contenu |
| `ext-gd` | toute, avec WebP et AVIF | Le rendu des images |
| `ext-mbstring` | toute | Les titres et les noms de fichiers, comptés en caractères |
| `ext-sodium` | toute | Les signatures en base64 sûr pour les URL |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | Les médias, les versions et les collections ; le type de colonne `phil_media` |
| `league/flysystem` | ^3.30 | Les fichiers sur un système de fichiers Flysystem : un répertoire local, un compartiment S3 |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/console` | ^8.2 | `phil:media:placeholders` |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | Le contrôleur et ses routes |
| `symfony/security-csrf` | ^8.2 | La vérification CSRF sans état des formulaires de l'explorateur |
| `symfony/security-core` | ^8.2 | L'attribut de rôle ou de *voter* qu'exige l'explorateur ; la sécurité de Symfony, par SecurityBundle, doit être présente pour que l'explorateur s'ouvre |
| `symfony/process` | ^8.2 | `cwebp` et `avifenc`, lancés sans shell |
| `symfony/uid` | ^8.2 | Les identifiants |
| `symfony/service-contracts` | ^3.6 | La trace du profileur, réinitialisée entre les requêtes |
| `psr/clock` | ^1.0 | Les dates de téléversement et l'expiration des URL signées ; le service `clock` du conteneur, qu'enregistre FrameworkBundle une fois `symfony/clock` installé |
| `twig/twig` | facultatif | `media_url()`, `media_schema()`, l'explorateur de fichiers |
| `symfony/ux-twig-component` | facultatif | Les composants `Img` et `Picture` |
| `symfony/form` | facultatif | `MediaPickerType` |
| `symfony/validator` | facultatif | La contrainte `MediaFile` |
| `symfony/asset-mapper` | facultatif | Les contrôleurs Stimulus, sans étape de construction |
| `symfony/web-link` | facultatif | L'image principale d'une page préchargée, avec `priority` |
| `easycorp/easyadmin-bundle` | facultatif, ^5.8 | `MediaField`, `MediaAdmin` |
| `phil/browser-security-bundle` | facultatif | Chaque fichier servi sous sa politique « contenu d'usager » |
| `phil/creation-disclosure` | facultatif | Comment un média a été fait, par un type de source numérique de l'IPTC |
| `phil/svg-sanitizer` | facultatif | Les images SVG purifiées et allégées avant d'être gardées, et servies comme des images |
| libvips, `vips` et `vipsheader` | facultatif | Le rendu avec `processor: vips` ou `auto` |

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

[Formats et URL](formats.md) décrit chaque transformation, [Stockage](storage.md) les systèmes de fichiers Flysystem.

La version de ce bundle qui ajoute le type de source numérique et l'aperçu ajoute deux colonnes nullables à
`phil_media`, `digital_source_type` et `placeholder` : une migration Doctrine les ajoute à une table existante.

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

Chaque partie facultative est branchée quand ce dont elle a besoin est installé : le type de colonne `phil_media` avec
DoctrineBundle, le thème de formulaire du sélecteur avec Twig et le composant de formulaire, les contrôleurs Stimulus
avec AssetMapper, les composants avec `symfony/ux-twig-component`, le préchargement de l'image principale avec
`symfony/web-link`, le champ et le lien de menu EasyAdmin avec EasyAdmin, le panneau du profileur en mode débogage.

Avec DoctrineBundle, le bundle mappe lui-même ses entités. Les migrations de l'application créent trois tables :

| Table | Contient |
|---|---|
| `phil_media` | Un média : son fichier actuel, sa description, sa licence, sa divulgation de l'IA et son point focal |
| `phil_media_version` | Les fichiers qu'un média a eus avant, une ligne par remplacement, supprimées avec lui |
| `phil_media_collection` | Les collections, par une clé unique |

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une collection se trouve par sa clé et un fichier par
sa clé de stockage, chacun tel qu'il a été écrit : la collection `Press` n'est jamais trouvée pour `press`, et le
fichier stocké sous `2026/Photo.jpg` n'est jamais pris pour `2026/photo.jpg`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_media` | `id`, `collection`, `storage_key` |
| `phil_media_version` | `id`, `media`, `storage_key` |
| `phil_media_collection` | `id`, `collection_key` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Les colonnes de jointure `collection` et `media` sont converties avec les identifiants qu'elles référencent, et MySQL refuse de
changer un côté d'une clé étrangère avant l'autre. Mettez `SET FOREIGN_KEY_CHECKS = 0` au début du `up()` de la
migration et `SET FOREIGN_KEY_CHECKS = 1` à sa fin ; les clés étrangères restent, et sont vérifiées de nouveau une fois
les deux côtés convertis :

```php
public function up(Schema $schema): void
{
    $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
    // The statements doctrine:migrations:diff wrote.
    $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
}
```

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT storage_key, COUNT(*) AS row_count FROM phil_media
GROUP BY storage_key HAVING COUNT(DISTINCT CAST(storage_key AS BINARY)) > 1;
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.

Un service `MediaStorage` ou `ImageProcessor` propre à l'application, aliasé, remplace le répertoire ou GD.
