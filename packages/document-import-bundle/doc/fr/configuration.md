<!-- phil-dev source-sha256=1a5d256a77e0cd0caeaa08cb7d5fe504a8df34cc061dc5a772925b2d0e4ef125 -->
# Configuration

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    media:
        store: auto            # auto, library or folder
        prefix: 'media/'       # the folder store: the address before each file name
        collection: ~          # the media library: the key of the collection, created the first time
    formats:
        import: [docx, odt, fodt, rtf, pdf, markdown, html]
        export: [docx, odt]
    limits:
        entry_bytes: 67108864  # an entry of an archive, a stream of a PDF, a flat document or a picture, once decoded
        total_bytes: 268435456 # a file and everything decoded from it together
        depth: 100             # how deep groups, objects and forms may nest
        objects: 500000        # how many objects a PDF may declare
    styles:                    # the block each paragraph style becomes
        'Chapter Title': heading-1
        'Rules Box': quote
        'Author Note': omit
    report:
        title: 'What was not imported'
    scan: auto                 # auto, on or off: phil/upload-scan-bundle before each file is parsed
    async:
        enabled: false         # true, or any key below, turns it on
        transport: ~           # the Messenger transport ImportDocument is routed to
        directory: '%kernel.project_dir%/var/document-imports'
        store: ~               # a service implementing Phil\DocumentImportBundle\Application\Contract\PendingImports
    export:
        media_source: ~        # a service implementing Phil\DocumentImport\Contract\MediaSource
        temporary_directory: ~ # the system's by default
    svg:
        max_bytes: 4194304     # a larger SVG image is refused whole
        max_depth: 64          # an SVG image nested deeper is refused whole
        max_elements: 50000    # an SVG image with more elements is refused whole
        data_images: true      # PNG, JPEG, GIF, WebP and AVIF images in data: addresses
        animations: false      # SMIL animation
        link_schemes: [http, https, mailto]
```

| Option | Vérification | Effet |
|---|---|---|
| `media.store` | `auto`, `library` ou `folder` | `auto` conserve les images dans la médiathèque lorsque `PhilMediaBundle` est enregistré, sous le préfixe sinon ; `library` exige la médiathèque ; `folder` ne l'utilise jamais |
| `media.prefix` | | L'adresse d'une image dans le magasin de dossier est ce préfixe suivi de son nom de fichier |
| `media.collection` | Une clé de collection de `phil/media-bundle` : de 1 à 64 lettres minuscules, chiffres, `_` ou `-`, commençant par une lettre | La collection où vont les médias ; aucune par défaut |
| `formats.import` | Des valeurs de `Phil\DocumentImport\Format` : `docx`, `odt`, `fodt`, `rtf`, `pdf`, `markdown`, `html` | Les formats que lit `DocumentImports` ; tout autre est refusé avant qu'un lecteur l'ouvre. `docx` couvre la forme stricte d'Office Open XML, `fodt` l'OpenDocument plat |
| `formats.export` | `docx`, `odt` | Les formats qu'écrit `DocumentImports::export()` |
| `limits.entry_bytes`, `limits.total_bytes` | Au moins 1 | Les tailles de `Phil\DocumentImport\Limits` ; un téléversement plus grand que `total_bytes` est refusé avant d'être lu |
| `limits.depth` | Au moins 1 | La profondeur d'imbrication permise aux groupes RTF, aux objets PDF, aux arbres de pages et aux formulaires |
| `limits.objects` | Au moins 1 | Le nombre d'objets qu'un PDF peut déclarer dans ses tables de références croisées et ses flux d'objets |
| `styles` | Un nom de 1 à 255 caractères, pas seulement des espaces ; une valeur de `Phil\DocumentImport\StyleRole` : `paragraph`, `heading-1` à `heading-6`, `quote`, `code`, `omit` | `Phil\DocumentImport\StyleMap`, lue par les lecteurs DOCX, ODT, ODT plat et RTF avant leurs propres règles ; un style est nommé comme le montre le traitement de texte ou par son identifiant, sans égard à la casse ni aux espaces autour ; `omit` laisse de côté ses paragraphes et les signale |
| `report.title` | Un texte, pas seulement des espaces | Le titre du bloc `warning` qu'ajoute `DocumentImports::documentWithReport()`, et de `@PhilDocumentImport/report.html.twig` |
| `scan` | `auto`, `on` ou `off` | `auto` remet chaque fichier à l'analyseur de `phil/upload-scan-bundle` avant de le lire lorsque `PhilUploadScanBundle` est enregistré ; `on` exige ce bundle ; `off` ne l'utilise jamais |
| `async.enabled` | | Enregistre `DocumentImportQueue`, le *handler* d'`ImportDocument` et le magasin des fichiers ; exige `symfony/messenger` |
| `async.transport` | Le nom d'un transport : de 1 à 64 lettres, chiffres, `_`, `.` ou `-` | Y achemine `ImportDocument`, avant l'acheminement propre à l'application |
| `async.directory` | Non vide | L'endroit où `FilesystemPendingImports` garde les fichiers qui attendent le *worker* |
| `async.store` | L'identifiant d'un service | Le `PendingImports` de l'application, à la place du répertoire |
| `export.media_source` | L'identifiant d'un service | Le `MediaSource` où l'exportateur lit les octets de chaque image ; sans lui, chaque image est signalée et laissée de côté |
| `export.temporary_directory` | | L'endroit où l'exportateur écrit chaque archive avant de la relire, sur un PHP sans `ZipArchive::openString()` ; un PHP qui l'a écrit l'archive en mémoire |
| `svg.max_bytes`, `svg.max_depth`, `svg.max_elements` | Au moins 1 | Les limites de `Phil\SvgSanitizer\Policy` |
| `svg.data_images` | | `Policy::withDataImages()` |
| `svg.animations` | | `Policy::withAnimations()` lorsque l'option est vraie |
| `svg.link_schemes` | En minuscules ; jamais `javascript`, `vbscript`, `livescript`, `mocha`, `data` ni `file` | `Policy::withLinkSchemes()` ; une liste vide ne garde que les fragments |

Un document binaire Word 97–2003, ou un document Office protégé par un mot de passe, est refusé quelle que soit la liste
des formats : la bibliothèque ne lit ni l'un ni l'autre.

Les clés sous `styles` sont gardées telles qu'écrites : `Rules-Box` et `Rules_Box` nomment deux styles.

## Services

| Service | Classe | Public |
|---|---|---|
| `Phil\DocumentImportBundle\Application\DocumentImports` | `DocumentImports` | Oui |
| `Phil\DocumentImport\DocumentImporter` | `DocumentImporter` | Oui |
| `Phil\DocumentImport\DocumentExporter` | `DocumentExporter` | Oui |
| `Phil\SvgSanitizer\SvgSanitizer` | `SvgSanitizer` | Oui |
| `phil_document_import.svg_policy` | `Phil\SvgSanitizer\Policy` | Non |
| `Phil\DocumentImport\Limits` | `Limits` | Non |
| `Phil\DocumentImport\StyleMap` | `StyleMap` | Non |
| `Phil\DocumentImport\MediaFolder` | `MediaFolder` | Non |
| `Phil\DocumentImport\MediaStore` | Un alias de `MediaLibraryStore` ou de `MediaFolder` | Non |
| `Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore` | `MediaLibraryStore`, avec la médiathèque | Non |
| `phil_document_import.media_address` | `Closure`, avec la médiathèque | Non |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | Un alias de `UploadScanScreening`, avec l'analyseur | Non |
| `Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening` | `UploadScanScreening`, avec l'analyseur | Non |
| `Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension` | `ImportReportExtension`, étiqueté `twig.extension` | Non |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | `DocumentImportQueue`, avec `async` | Oui |
| `Phil\DocumentImportBundle\Application\ImportDocumentHandler` | `ImportDocumentHandler`, étiqueté `messenger.message_handler`, avec `async` | Non |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | Un alias de `FilesystemPendingImports` ou de `async.store`, avec `async` | Non |
| `Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports` | `FilesystemPendingImports`, avec `async` et sans `async.store` | Non |

`DocumentImporter` et `DocumentExporter` lisent et écrivent tous les formats que connaît la bibliothèque ;
`DocumentImports` applique `formats` et l'analyse préalable, et répartit le rapport.

`PhilMediaBundle` et `PhilUploadScanBundle` sont trouvés par leur nom dans la liste des bundles du noyau, quel que soit
l'ordre de `config/bundles.php`. Le service `SvgSanitizer` est celui qu'utilisent l'importateur et l'exportateur : un
formulaire qui accepte des téléversements SVG ailleurs dans l'application les assainit avec la même politique en le
demandant.
