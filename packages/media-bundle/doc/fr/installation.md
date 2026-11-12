<!-- phil-dev source-sha256=f6ba047ea9f04eb7f0f9da24f92ff84eb9669655146f320cc7d0cc4423bcfad5 -->
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

Un service `MediaStorage` ou `ImageProcessor` propre à l'application, aliasé, remplace le répertoire ou GD.
