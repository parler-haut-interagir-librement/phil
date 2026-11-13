<!-- phil-dev source-sha256=ec2943691c1d9499b1a9e9c71a699ccf1e2576c7709dfffa524b12532c3a962a -->
# phil/epub

**Des livres EPUB 3 à partir du HTML que vous avez déjà, valides pour EPUBCheck et lisibles par les technologies
d'assistance.**

phil/epub écrit des publications EPUB 3. Il prend des chapitres sous forme de fragments de HTML purifié, les
transforme en documents de contenu XHTML bien formés, et les empaquette avec leurs feuilles de style, leurs images et
leurs polices. Il écrit le document de paquet avec ses métadonnées (titre, langue, identifiants, créateurs, date de
modification) et ses métadonnées d'accessibilité schema.org ; le document de navigation avec sa table des matières,
ses repères et sa liste de pages ; la table des matières EPUB 2 sur demande ; le manifeste et l'épine ; une couverture ;
des fichiers audio et vidéo avec leurs solutions de repli et leurs pistes de texte ; des superpositions de médias qui
lisent le texte à voix haute en synchronisme ; des pages à mise en page fixe ; les polices, obscurcies par l'algorithme
de l'IDPF sur demande ; et le conteneur ZIP, avec son `mimetype` en premier et stocké sans compression. Les liens qui
traversent les chapitres sont réécrits vers le chapitre qui contient leur cible, et les adresses que le livre ne
contient pas sont retirées plutôt que laissées brisées.

Il s'adresse à toute application PHP qui publie de longs textes et veut qu'on les lise hors ligne, dans une
application de lecture ou sur une liseuse : un CMS qui exporte une série d'articles, un outil qui met en page des
livres de jeu, un wiki qui exporte une section, des archives d'infolettre. Un livre s'écrit en un seul appel, en
mémoire ou dans un fichier, et le même livre donne les mêmes octets. Une application qui stocke ses documents en UJO
(Universal JSON Output), le format de document par blocs de `phil/ujo`, les transforme en chapitres par `phil/ujo`.

C'est une simple bibliothèque PHP qui n'a besoin que des extensions `dom` et `zip` de PHP ;
`phil/ujo` est une dépendance facultative. La spécification EPUB 3.3, EPUB Accessibility 1.1 et l'algorithme
d'obscurcissement des polices de l'IDPF sont ses références ; aucun code d'une autre bibliothèque EPUB n'est utilisé.

La conception est petite : un `Book` contient ses `Metadata` (avec une valeur `Accessibility`), sa liste de `Chapter`
(chacun avec son `MediaOverlay` quand il est narré), ses fichiers `Asset`, sa `Cover` et sa `Rendition` ;
`EpubWriter` l'écrit. `HtmlSplitter` coupe un long fragment en chapitres à ses titres, et `Ujo\UjoChapters` fait de
même avec un document UJO, dont les sauts de page deviennent la liste de pages.

## État

| Partie | État |
|---|---|
| Conteneur, `mimetype` en premier et stocké sans compression, entrées datées de la date de modification, octets reproductibles | Implémenté et testé |
| Document de paquet : titre, langue, identifiants (UUID, ISBN-13, DOI, URN, propre), créateurs et collaborateurs avec rôles MARC, éditeur, description, date, sujets, droits, source, date de modification, direction | Implémenté et testé |
| Métadonnées d'accessibilité : modes d'accès, modes suffisants, fonctionnalités, dangers, résumé, conformité, certificateur, avec l'ajout de ce que sait le rédacteur | Implémenté et testé |
| Document de navigation : table des matières tirée des titres ou donnée, repères, liste de pages | Implémenté et testé |
| XHTML à partir de HTML purifié : analysé comme HTML5, écrit comme XML, SVG et MathML dans leur espace de noms, scripts et gestionnaires retirés, identifiants dédoublonnés | Implémenté et testé |
| Table des matières EPUB 2 (`toc.ncx`) avec sa liste de pages, pour les anciens systèmes de lecture | Implémenté et testé |
| Liens entre chapitres, ressources vérifiées contre le livre, adresses associées par l'application, audio et vidéo distants | Implémenté et testé |
| Feuilles de style, images et polices dans les types de médias de base d'EPUB, lus d'après leurs octets ; obscurcissement des polices ; couverture | Implémenté et testé |
| Fichiers audio et vidéo dans le livre, lus d'après leurs octets, avec solutions de repli du manifeste ; pistes de texte WebVTT | Implémenté et testé |
| Superpositions de médias : documents SMIL, durées, narrateurs et classes actives dans le document de paquet | Implémenté et testé |
| Mise en page fixe : livres ou chapitres prépaginés, fenêtre d'affichage par page, orientation, double page et côté de la double page | Implémenté et testé |
| Chapitres coupés aux titres ; documents UJO par `phil/ujo`, avec les sauts de page comme liste de pages | Implémenté et testé |
| Sortie vérifiée par EPUBCheck 5.4 | Testé quand EPUBCheck est installé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Livres](doc/fr/books.md) | `Book`, `Metadata`, les identifiants, les créateurs, les chapitres, les ressources, la couverture et l'obscurcissement des polices. |
| [Accessibilité](doc/fr/accessibility.md) | Les métadonnées d'accessibilité, ce qu'ajoute le rédacteur, et ce qui rend le contenu accessible. |
| [Documents de contenu](doc/fr/content.md) | Comment le HTML devient du XHTML, les liens et les ressources, l'association des adresses, et le découpage d'un fragment en chapitres. |
| [Navigation](doc/fr/navigation.md) | La table des matières, les repères, la liste de pages, leurs libellés et la table des matières EPUB 2. |
| [Médias, superpositions et mise en page](doc/fr/media.md) | L'audio, la vidéo et les pistes de texte, les solutions de repli, les superpositions de médias et la mise en page fixe. |
| [Documents UJO](doc/fr/ujo.md) | `UjoChapters` : un document UJO rendu par `phil/ujo` et découpé en chapitres avec ses pages. |
| [Exceptions](doc/fr/exceptions.md) | `EpubException` et ses deux exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, EPUBCheck, le seuil de mutation, et comment tester du code qui écrit des livres. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/epub
```

```php
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Creator;
use Phil\Epub\EpubWriter;
use Phil\Epub\Identifier;
use Phil\Epub\Metadata;

$book = new Book(new Metadata(
    'The Lighthouse Keeper',
    'en',
    Identifier::uuid('7c9e6679-7425-40de-944b-e07fc1f90ae7'),
    new DateTimeImmutable(),
    creators: [new Creator('Ann Writer', 'aut')],
))
    ->withAsset(Asset::stylesheet('css/book.css', 'body { line-height: 1.5; }'))
    ->withChapter(
        new Chapter('<h1>The Storm</h1><p>The light went out at midnight.</p>'),
        new Chapter('<h1>The Morning</h1><p>See <a href="#the-boat">the boat</a>.</p><h2 id="the-boat">The Boat</h2>'),
    );

new EpubWriter()->write($book, 'lighthouse.epub');
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `dom` et `zip`. `phil/ujo` pour les documents UJO.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
