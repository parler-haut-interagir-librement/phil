<!-- phil-dev source-sha256=836bbb9efe1ed11589b8e01059218f6ced41446455538a29f08cbc900b9f2ec7 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilMediaBundle` | `final class`, `AbstractBundle` | Alias `phil_media` |
| `Domain\Model\Media`, `MediaVersion`, `MediaCollection` | entités | Un média ; un fichier précédent ; une collection |
| `Domain\ValueObject\FileInfo` | valeur | Le nom, le type de média, la taille et les dimensions d'un fichier |
| `Domain\ValueObject\MediaId` | valeur | L'identifiant d'un média, tel que le contient une colonne `phil_media` |
| `Domain\Media\AiDisclosure` | enum | `None`, `Assisted`, `Generated` |
| `Domain\Repository\MediaRepository` | interface | Enregistrer, trouver, lister |
| `Domain\Exception\MediaException`, `InvalidMedia`, `MediaInUse` | exceptions | Tout ce que lance le bundle |
| `Application\MediaLibrary` | `final readonly class` | Téléverser, remplacer, supprimer, lire, rendre |
| `Application\ImageFormat`, `RenderBox` | valeurs | Un format : ses transformations, sa qualité et ses largeurs plus étroites ; la partie d'une image que garde une vignette et la taille rendue |
| `Application\Transform\Transformer` | interface | Une transformation : le `Step` qu'elle dessine sur la `Geometry` d'une image, et sa définition |
| `Application\Transform\Thumbnail`, `Crop`, `Resize`, `Widen`, `Heighten`, `Expand` | valeurs | Les transformations |
| `Application\Transform\Transformers` | `final class` | Les transformations lues depuis leur configuration ; `TYPES` |
| `Application\Transform\Geometry`, `Step` | valeurs | La taille et le point focal d'une image ; une partie de celle-ci dessinée sur un nouveau canevas |
| `Application\UrlSigner` | `final readonly class` | Signe et vérifie les URL |
| `Application\MediaReferences`, `MediaReference`, `DeleteBehaviour` | interface, valeur, enum | Les enregistrements qui montrent un média, colonne par colonne, et ce que leur fait une suppression |
| `Application\MediaSchema` | `final class` | Le média sous forme de données schema.org |
| `Application\MediaStorage`, `ImageProcessor` | interfaces | Où les fichiers sont gardés ; comment les images sont rendues |
| `Application\Contract\SvgPurifier` | interface | Comment une image SVG est purifiée avant d'être gardée |
| `Infrastructure\Storage\FilesystemStorage` | adaptateur | Un répertoire |
| `Infrastructure\Storage\FlysystemStorage` | adaptateur | Un système de fichiers Flysystem : local, S3, SFTP |
| `Infrastructure\Image\GdImageProcessor` | adaptateur | GD, avec une limite de pixels et des encodeurs binaires facultatifs |
| `Infrastructure\Image\BinaryEncoder` | `final readonly class` | `cwebp` ou `avifenc`, trouvés dans le `PATH` ou nommés |
| `Infrastructure\Image\VipsImageProcessor` | adaptateur | libvips par `vips` et `vipsheader`, trouvés dans le `PATH` ou nommés |
| `Infrastructure\Image\ImageProcessors` | `final class` | Le traitement que choisit la configuration : GD, libvips, ou libvips quand il est trouvé |
| `Infrastructure\Svg\SanitizingSvgPurifier` | adaptateur | `phil/svg-sanitizer`, puis `SvgMinifier` |
| `Infrastructure\Svg\SvgMinifier` | `final readonly class` | Commentaires, `metadata` et blancs retirés, coordonnées arrondies |
| `Infrastructure\Doctrine\DoctrineMediaRepository` | adaptateur | Doctrine ORM |
| `Infrastructure\Doctrine\MediaType` | type DBAL | Le type de colonne `phil_media` |
| `Infrastructure\Doctrine\DoctrineMediaReferences` | adaptateur | Les colonnes `phil_media` des entités mappées |
| `Infrastructure\Validator\MediaFile`, `MediaFileValidator` | contrainte | Existence, collections et types de médias |
| `Infrastructure\Twig\Component\Img`, `Picture` | composants Twig | `PhilMedia:Img`, `PhilMedia:Picture` |
| `Infrastructure\Twig\ResponsiveImages`, `ImageCandidates` | service, valeur | L'URL, le `srcset` et la taille de chaque type de fichier ; l'aperçu et sa position ; le préchargement d'une image prioritaire |
| `Infrastructure\Console\PlaceholdersCommand` | commande de console | `phil:media:placeholders` : un aperçu minuscule pour les images gardées sans aperçu |
| `Infrastructure\Admin\MediaAdminController` | contrôleur | L'explorateur de fichiers et son sélecteur ; `INDEX_ROUTE`, `VIEWS`, `OUTCOMES` |
| `Infrastructure\Admin\ExplorerAccess` | service | Qui peut utiliser l'explorateur, selon l'attribut configuré, et si un formulaire en provient, selon son origine et le jeton CSRF sans état `phil_media_explorer` |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | passe de compilation | Arrête le conteneur avec un message qui dit quoi changer quand `framework.csrf_protection` vaut false |
| `Infrastructure\Form\MediaPickerType` | type de formulaire | Le champ qui ouvre le sélecteur |
| `Infrastructure\EasyAdmin\MediaField`, `MediaFieldConfigurator` | champ EasyAdmin | Un média dans un contrôleur CRUD |
| `Infrastructure\EasyAdmin\MediaAdmin` | `final class` | Le lien d'un menu EasyAdmin vers l'explorateur de fichiers |
| `Infrastructure\Profiler\MediaDataCollector`, `MediaTrace`, `MediaReport`, `TraceableMediaStorage`, `TraceableImageProcessor` | profileur | Le panneau des médias, en mode débogage |
| `Infrastructure\Http\MediaController` | contrôleur | Les URL signées ; `INLINE_TYPES`, les types qu'un navigateur peut afficher sans la politique « contenu d'usager » ; les images SVG purifiées en `image/svg+xml` |
| `Infrastructure\Twig\MediaExtension` | fonctions Twig | `media_url()` (le fichier, un format, une largeur plus étroite), `media_schema()` |
