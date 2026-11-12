<!-- phil-dev source-sha256=ceb65f4df412cb062a5d6de8d725e3229c9f7819dbb2e307a9881ce92e78bc26 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `FileInfo`, `Media`, `MediaVersion`, `MediaCollection` | Chaque vérification et chaque borne ; les versions ; les descriptions, les licences, les points focaux ; les tables et les colonnes |
| `ImageFormat`, `RenderBox`, `UrlSigner` | Chaque règle ; recadrages et ajustements, arrondis, jamais d'agrandissement ; signatures et expiration |
| Les transformations, `Transformers` | Chaque étape, le point focal qui suit, les arrondis, `upscale`, les positions et les arrière-plans, chaque définition refusée |
| `MediaLibrary`, `DoctrineMediaRepository` | Téléversements, remplacements, suppressions qui laissent les autres médias intacts, rendus gardés, collections une page à la fois |
| `FilesystemStorage`, `FlysystemStorage` | Des clés qui restent dans le répertoire ou le système de fichiers ; les échecs de Flysystem |
| `GdImageProcessor`, `BinaryEncoder` | Le point focal dans le cadre, la transparence, chaque type de fichier, les transformations en chaîne ; les arguments des encodeurs, le répertoire privé et le nettoyage, par un binaire de remplacement, et `cwebp` et `avifenc` eux-mêmes quand ils sont installés |
| `MediaController`, `MediaExtension` | URL signées et vérifiées, largeurs plus étroites, expiration, `404`, en-têtes de cache |
| `MediaId`, `MediaType`, `DoctrineMediaReferences` | Identifiants refusés ; la colonne lue et écrite ; chaque colonne trouvée, objets intégrables et superclasses mappées compris, comptée, vidée ; chaque comportement d'une suppression |
| `MediaFile` | Chaque violation et son code, les motifs, le média hors de toute collection, les valeurs refusées |
| `ResponsiveImages`, `Img`, `Picture` | Les tailles selon le point focal, le `srcset`, les solutions de repli, les médias qui ne peuvent pas être rendus ou qui n'existent plus |
| `MediaAdminController`, `ExplorerAccess`, `MediaPickerType` | Grille, liste, pages, sélecteur ; téléversements de plusieurs fichiers à la fois, trop gros, refusés ; collections, renommage, déplacement, suppression, un média utilisé ; formulaires venus d'un autre site, sans leur jeton CSRF ou avec un mauvais ; chaque liste et chaque changement refusés sans l'attribut, avec le sujet demandé ; les données et le widget du champ |
| `MediaField`, `MediaFieldConfigurator` | Les options du champ, le média trouvé à partir de chaque genre de valeur, la collection, le gabarit avec et sans média |
| Le profileur | La trace de chaque opération, les succès de cache, le panneau rendu sur une mise en page de profileur de remplacement |
| `PhilMediaBundle` | Les services, les valeurs par défaut, les réglages refusés, le mode débogage, les réglages ajoutés en tête pour Doctrine, Twig et AssetMapper |
| `VipsImageProcessor`, `ImageProcessors` | Les commandes données à libvips pour chaque étape, l'alpha et le JPEG, la qualité et les métadonnées, ce qui ne l'atteint jamais, les échecs et le nettoyage, par des doublures de `vips` et `vipsheader` ; libvips lui-même quand il est installé |
| Les aperçus | Un par version, calculé une fois, aucun sans format, pour un fichier qui n'est pas une image matricielle, qui ne peut pas être rendu ou dont l'aperçu est trop grand ; l'aperçu et sa position confiés aux composants, jamais un `style` ; `phil:media:placeholders` sur plusieurs pages et collections, sa limite, son essai à blanc, ses échecs et ses refus |
| `priority` | `loading`, `decoding` et `fetchpriority`, ce qu'une valeur explicite garde ; le lien de préchargement d'un `Img`, de la première source d'un `Picture` et d'un fichier qui ne peut pas être rendu, à côté des liens déjà là, rien sans requête ou sans fournisseur qui évolue |
| `SanitizingSvgPurifier`, `SvgMinifier`, les téléversements SVG | Ce qui est retiré, le texte gardé, chaque cas d'arrondi, les refus ; une image SVG servie purifiée en `image/svg+xml`, une image gardée sans purification purifiée à son premier service, puis relue, de nouveau pour une nouvelle version, une image refusée en `404`, un flux d'octets sans purificateur |
| La politique « contenu d'usager » | Les en-têtes de l'original et d'un format, ce qui est affiché sous la politique par défaut et sous une politique propre |
| `getMany()` | L'ordre demandé, les doublons et les identifiants inconnus, une requête par 500 |
| Comment un média a été fait | La divulgation de l'IA selon chaque sorte de type de source numérique, les types retirés, schema.org |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, Twig et les composants Twig :
une image est téléversée, ses `media_url()` sont rendus par Twig et demandés par le noyau, les composants `Img` et
`Picture` sont rendus, et l'explorateur est listé, utilisé pour renommer (refusé sans jeton, accepté avec un jeton et
son témoin, que la réponse efface) et ouvert comme sélecteur. Dans son environnement `user_content`, le noyau ajoute
`phil/browser-security-bundle` et les aperçus : les fichiers reviennent sous la politique « contenu d'usager », un SVG
purifié et servi en `image/svg+xml`, les composants avec leur aperçu pour le contrôleur `placeholder` et les contrôleurs
propres à l'image gardés ; le test est sauté sans le bundle. Les composants avec `priority` sont rendus aussi.

La cible de fuzzing `tests/Fuzz/svg-purifier.php` nourrit le purificateur SVG de ce que personne n'a écrit : elle rend
un élément SVG qui revient identique quand il est purifié de nouveau, ou lance `InvalidMedia`, et rien d'autre.

`tests/js` lance les modules purs des contrôleurs sous l'exécuteur de tests de Node : les tailles de fichiers, le jeton
CSRF soumis en double, et l'aperçu et la position qu'accepte le contrôleur `placeholder`. Les trois contrôleurs de
l'explorateur sont vérifiés à la main dans un navigateur : basculer la vue la garde après un rechargement ; déposer
trois fichiers montre trois barres de progression, puis la liste qui les contient ; un fichier qui dépasse
`max_upload_bytes` est refusé avant d'être envoyé ; le sélecteur s'ouvre dans une boîte de dialogue, y suit un lien de
collection et remplit le champ. La façon dont le contrôleur `placeholder` peint et retire l'aperçu dans un navigateur
n'a aucun test automatisé.

```sh
vendor/bin/phpunit packages/media-bundle/tests
node --test tests/js     # from packages/media-bundle
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/media-bundle/tests/Benchmark
```

`BatchReadBench` compare un `get()` par média à un seul `getMany()` pour 12, 48 et 200 médias ; `VipsRenderBench`
compare GD et libvips sur une vignette d'une photo de 1920 × 1080 et de 6000 × 4000, et ne mesure rien pour libvips
quand il n'est pas installé.
