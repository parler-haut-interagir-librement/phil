<!-- phil-dev source-sha256=634545d4be24d0c7769318bc951424207ac34cd4d93b6592552877215bd27d60 -->
# phil/media-bundle

**Des fichiers et des images téléversés, décrits, versionnés et servis à des URL signées dans les formats que vous
déclarez.**

phil/media-bundle est une médiathèque pour une application Symfony. Les fichiers sont gardés dans un répertoire ou sur
n'importe quel système de fichiers Flysystem, dont un compartiment S3, et décrits dans Doctrine : leurs versions, leur
collection, un titre, un texte de remplacement et un crédit, une licence, et ce qu'ils doivent à l'intelligence
artificielle. Les images sont rendues sur demande dans les formats que déclare l'application (transformations en chaîne,
recadrage autour d'un point focal, redimensionnement, agrandissement du canevas, et largeurs plus étroites pour un
`srcset`), jamais agrandies sauf indication contraire. Elles sortent en WebP, AVIF, JPEG ou PNG, tirées de l'original
chaque fois par GD, au travers de `cwebp` et `avifenc` quand ils sont installés, ou par libvips. Chaque version d'une
image peut recevoir un aperçu minuscule, peint derrière l'image pendant qu'elle se charge par un contrôleur Stimulus,
sans attribut `style` qu'une politique de sécurité du contenu stricte ferait tomber, et `phil:media:placeholders` en
donne un aux images gardées avant ; une image SVG est purifiée et allégée avant d'être gardée, quand
`phil/svg-sanitizer` est installé, et servie comme une image. Les composants `Img` et `Picture` les affichent
dimensionnées, en chargement différé et avec un `srcset`, ou récupérées en premier et préchargées pour l'image
principale d'une page. Un explorateur de fichiers, derrière un rôle ou un *voter* et les jetons CSRF sans état de
Symfony, les téléverse, les renomme, les déplace et les supprime, s'ouvre comme sélecteur pour un champ de formulaire ou
un champ EasyAdmin, et est lié depuis un menu EasyAdmin ; une colonne `phil_media` permet à n'importe quelle entité de
pointer vers un média, vérifiée par une contrainte. Le bundle ne décide pas de la façon dont
la divulgation de l'IA est montrée aux visiteurs, et supprimer un média le retire pour de bon : le mettre à la corbeille
est le choix de l'application, avec `phil/trash-bundle`.

Il s'adresse à un site où des rédacteurs téléversent des images et des documents que des pages affichent ensuite, et où
ces pages doivent rester rapides et légères sur un téléphone. Remplacer un fichier garde l'identifiant du média : chaque
page qui le montre montre donc le nouveau fichier, tandis que le fichier précédent reste enregistré comme version. Le
texte de remplacement, le crédit et la licence sont sur le média lui-même, ce qui fait des images accessibles et
correctement créditées le chemin facile. Un enregistrement qui montre encore un média décide de ce que fait sa
suppression : la refuser, perdre le média, ou garder son identifiant.

Le bundle se branche sur Doctrine ORM pour ses enregistrements, sur le routage et le noyau HTTP de Symfony pour
`MediaController`, sur Twig pour `media_url()`, `media_schema()` et l'explorateur quand Twig est installé, et sur les
composants Twig, les composants de formulaire et de validation, AssetMapper, EasyAdmin et le profileur quand ils le
sont. Le stockage et le traitement des images sont des ports, `MediaStorage` et `ImageProcessor`, avec un adaptateur
répertoire, un adaptateur Flysystem, un adaptateur GD et un adaptateur libvips. Avec `phil/creation-disclosure`, un
média déclare comment il a été fait par un type de source numérique de l'IPTC ; avec `phil/browser-security-bundle`,
chaque fichier est servi sous sa politique « contenu d'usager ». Chaque URL porte la version du média et une signature
HMAC-SHA256 de son chemin : une réponse ne change donc jamais, et un navigateur ou un CDN peut la garder un an. Avec
`url_ttl`, la signature couvre aussi le moment où l'URL expire, et la réponse n'est pas gardée plus longtemps. Une URL
que l'application n'a pas donnée répond `404`, et un fichier qui pourrait exécuter un script dans le navigateur, comme
une page HTML, est téléchargé plutôt qu'affiché ; une image SVG purifiée s'affiche dans un `<img>` et est enregistrée
quand on l'ouvre directement.

## État

| Partie | État |
|---|---|
| `Media`, `MediaVersion`, `MediaCollection`, `FileInfo`, `AiDisclosure` | Implémentés et testés |
| `MediaLibrary` : téléverser, remplacer, supprimer, rendre ; `ImageFormat`, `RenderBox` | Implémentés et testés |
| Transformations : vignette, recadrage, redimensionnement, élargissement, rehaussement, agrandissement du canevas ; largeurs plus étroites | Implémentées et testées |
| `FilesystemStorage`, `FlysystemStorage`, `GdImageProcessor`, `BinaryEncoder`, `DoctrineMediaRepository` | Implémentés et testés |
| URL signées : `UrlSigner`, `MediaController`, `media_url()`, `media_schema()` | Implémentées et testées |
| Composants `Img` et `Picture`, `ResponsiveImages` ; `priority`, préchargé avec `symfony/web-link` | Implémentés et testés |
| Le type de colonne `phil_media`, `MediaFile`, les comportements à la suppression | Implémentés et testés |
| L'explorateur de fichiers, `MediaPickerType`, les contrôleurs Stimulus | Implémentés et testés ; les contrôleurs vérifiés à la main |
| Le champ EasyAdmin, `MediaField` ; le lien du menu vers l'explorateur, `MediaAdmin` | Implémentés et testés |
| Le panneau du profileur | Implémenté et testé |
| `VipsImageProcessor`, `ImageProcessors` : libvips par ses outils en ligne de commande | Implémenté et testé avec des doublures des outils ; rendu par libvips là où il est installé |
| Les aperçus : un aperçu WebP minuscule par version, le contrôleur Stimulus `placeholder`, `phil:media:placeholders` | Implémentés et testés ; la peinture du contrôleur non vérifiée dans un navigateur |
| Les images SVG purifiées et allégées : `SanitizingSvgPurifier`, `SvgMinifier` ; servies en `image/svg+xml` | Implémentées et testées |
| La politique « contenu d'usager » de `phil/browser-security-bundle` sur chaque fichier servi | Implémenté et testé |
| `MediaRepository::getMany()` : plusieurs médias en une requête | Implémenté et testé |
| Comment un média a été fait, par un type de source numérique de l'IPTC de `phil/creation-disclosure` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, configuration, routes, tables. |
| [La médiathèque](doc/fr/library.md) | Téléverser, remplacer, décrire, attribuer une licence, les collections, supprimer. |
| [Formats et URL](doc/fr/formats.md) | Les formats et leurs transformations, le point focal, le rendu et les encodeurs, les URL signées, le cache. |
| [Stockage](doc/fr/storage.md) | Le répertoire, les systèmes de fichiers Flysystem (local, S3), un stockage propre à l'application. |
| [Images adaptatives](doc/fr/images.md) | Les composants `Img` et `Picture` : tailles, `srcset`, types de fichiers, solutions de repli, priorité, aperçus. |
| [Enregistrements qui montrent des médias](doc/fr/doctrine.md) | Le type de colonne `phil_media`, la contrainte `MediaFile`, ce que fait une suppression. |
| [L'explorateur de fichiers](doc/fr/admin.md) | Routes, liste, téléversements, les contrôleurs Stimulus, le sélecteur et son champ de formulaire. |
| [EasyAdmin](doc/fr/easyadmin.md) | `MediaField` : un média dans un contrôleur CRUD, avec le sélecteur dans son formulaire ; l'explorateur dans le menu. |
| [Le panneau du profileur](doc/fr/profiler.md) | Ce qu'une requête a rendu et lu, en mode débogage. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le bundle, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$media = $library->upload($uploadedFile->getPathname(), $uploadedFile->getClientOriginalName(), $collection);
$media->describe('The party', alt: 'People dancing under paper lanterns', credit: 'Ada');
$media->license('CC-BY-4.0', AiDisclosure::Assisted);
$media->focus(0.3, 0.4);
$repository->save($media);
```

```twig
<twig:PhilMedia:Picture :media="post.cover" format="card" sizes="(min-width: 60em) 50vw, 100vw"/>
<twig:PhilMedia:Img :media="page.hero" format="hero" sizes="100vw" priority/>
<img src="{{ media_url(media, 'thumbnail', 'avif') }}" alt="{{ media.alt }}">
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-fileinfo`, `ext-gd`, `ext-mbstring` et `ext-sodium` ; Doctrine ORM 3.7 ;
Flysystem 3 ; la console, l'injection de dépendances, la configuration, HTTP Foundation et le noyau HTTP, Process, le
routage, les contrats de services et UID de Symfony 8.2 ; l'horloge de PSR-20. Twig, les composants Twig, les
composants de formulaire et de validation, AssetMapper, WebLink, EasyAdmin, `phil/browser-security-bundle`,
`phil/creation-disclosure`, `phil/svg-sanitizer` et libvips sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
