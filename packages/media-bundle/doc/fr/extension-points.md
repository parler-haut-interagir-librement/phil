<!-- phil-dev source-sha256=3fb6e7042a5ba1ab641796ef9480877337e719f235b5df96899cc071ecd7ef1e -->
# Points d'extension

Ce qu'une application implémente, remplace, configure ou redéfinit. Chaque port est un alias que remplace un service
propre à l'application, aliasé à l'interface.

## Ports

### `MediaStorage`

`Phil\MediaBundle\Application\MediaStorage` : l'endroit où les fichiers sont gardés, par clé :
`originals/{id}/{version}/{name}`, `formats/{id}/…`. `FilesystemStorage` sur `storage_dir`, ou `FlysystemStorage` sur
le service `filesystem`. Voir [Stockage](storage.md).

| Méthode | Contrat |
|---|---|
| `write(string $key, string $contents): void` | Garde le fichier sous la clé, en remplaçant celui qui s'y trouve |
| `read(string $key): ?string` | Son contenu, ou `null` s'il n'y en a pas |
| `deletePrefix(string $prefix): void` | Supprime chaque fichier dont la clé commence ainsi |

### `ImageProcessor`

`Phil\MediaBundle\Application\ImageProcessor` : dessine un format. `GdImageProcessor` dessine avec GD et confie le WebP
et l'AVIF à `cwebp` et `avifenc` quand ils sont installés. Voir [Formats et URL](formats.md#le-rendu).

| Méthode | Contrat |
|---|---|
| `render(string $image, ImageFormat $format, array $focalPoint, string $type): string` | Les octets de l'image dessinés au format autour du point focal (de 0 à 1 en largeur et en hauteur), en `webp`, `avif`, `jpeg` ou `png` |

Un traitement d'images propre à l'application borne ce qu'il décode, comme `GdImageProcessor` refuse une image plus
grande que `max_pixels` avant de la décoder : les fichiers viennent de téléversements. `VipsImageProcessor` est
l'autre adaptateur, choisi par `processor`.

### `SvgPurifier`

`Phil\MediaBundle\Application\Contract\SvgPurifier` : purifie une image SVG avant qu'elle soit gardée.
`SanitizingSvgPurifier`, branché quand `phil/svg-sanitizer` est installé, purifie puis allège.

| Méthode | Contrat |
|---|---|
| `purify(string $svg): string` | L'image à garder ; `InvalidMedia` quand elle est refusée en entier |

### `MediaReferences`

`Phil\MediaBundle\Application\MediaReferences` : quels enregistrements montrent un média, demandé avant une
suppression. `DoctrineMediaReferences` lit chaque colonne `phil_media` des entités mappées ; `NoMediaReferences` sert
sans Doctrine. Voir [Enregistrements qui montrent des médias](doctrine.md#ce-que-fait-une-suppression).

| Méthode | Contrat |
|---|---|
| `of(MediaId $id): list<MediaReference>` | Les colonnes qui contiennent le média, chacune avec son nombre d'enregistrements et son comportement |
| `detach(MediaId $id, MediaReference $reference): void` | Vide cette colonne dans chaque enregistrement qui contient le média |

### `MediaRepository`

`Phil\MediaBundle\Domain\Repository\MediaRepository` : les médias, les versions et les collections, un alias public de
`DoctrineMediaRepository`. Le dépôt propre à une application implémente chaque méthode, `getMany()` comprise.

| Méthode | Contrat |
|---|---|
| `getMany(string ...$ids): array<string, Media>` | Les médias trouvés, par identifiant, dans l'ordre demandé ; chacun lu une fois, les inconnus laissés de côté |

### `Transformer`

`Phil\MediaBundle\Application\Transform\Transformer` : une transformation d'un format, sous forme de `Step` : une partie
de l'image copiée, mise à l'échelle, dans une partie d'un nouveau canevas. La configuration nomme les types fournis
(`thumbnail`, `crop`, `resize`, `widen`, `heighten`, `expand`) ; en PHP, `ImageFormat::transforming()` prend n'importe
quel `Transformer`.

| Méthode | Contrat |
|---|---|
| `step(Geometry $image): Step` | L'étape qui dessine l'image telle que l'a laissée la transformation précédente |
| `definition(): array` | Ce qu'elle est, telle que configurée : `['type' => 'crop', 'width' => 300, …]` |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `formats`, `original` | Les variations des images, leurs transformations, leur qualité et leurs largeurs |
| `storage_dir`, `filesystem` | L'endroit où les fichiers sont gardés |
| `secret`, `url_ttl` | Les URL signées |
| `max_pixels` | La plus grande image décodée |
| `encoders` | Les encodeurs WebP et AVIF |
| `processor`, `vips_binary` | GD ou libvips |
| `placeholder` | Les aperçus minuscules : actifs ou non, leur boîte et leur qualité |
| `svg` | La purification des SVG, active ou non, et la précision des coordonnées |
| `delete_behaviour` | Ce que fait une suppression aux enregistrements qui montrent un média, par colonne |
| `admin` | La mise en page de l'explorateur de fichiers, la taille de page, le plus gros téléversement et l'attribut d'accès |

Voir [Installation](installation.md).

## Gabarits, formulaires et composants

| Point | Comment |
|---|---|
| Images adaptatives | Les composants `Img` et `Picture`, leur `priority`, leur aperçu et son contrôleur Stimulus `placeholder` ; voir [Images adaptatives](images.md) |
| Contenu d'usager | La politique de `phil/browser-security-bundle`, configurée là ; voir [Formats et URL](formats.md#sous-la-politique-contenu-dusager) |
| L'explorateur de fichiers | Son `layout`, un gabarit de l'application avec un bloc `content` ; les gabarits du bundle redéfinis dans `templates/bundles/PhilMediaBundle/` ; voir [L'explorateur de fichiers](admin.md) |
| Le champ de sélection | `MediaPickerType` et sa `collection` ; son thème `@PhilMedia/form/media_picker.html.twig` ; ce qui peut être choisi limité par `MediaFile` |
| EasyAdmin | Le champ de média et son configurateur, le lien de menu `MediaAdmin::menuItem()` ; voir [EasyAdmin](easyadmin.md) |
