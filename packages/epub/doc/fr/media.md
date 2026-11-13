<!-- phil-dev source-sha256=c8dc53d73adc673575ca1549d2c66133197cb650f046ceda067c5746fe590537 -->
# Médias, superpositions et mise en page

Un livre peut contenir plus que du texte et des images : des fichiers audio et vidéo que ses chapitres jouent, des
sous-titres pour eux, une narration qui lit le texte à voix haute pendant que le système de lecture le surligne, et
des pages mises en page une fois pour toutes, comme dans une bande dessinée, un album illustré ou une feuille de jeu.

## Audio et vidéo

`Asset::audio()` et `Asset::video()` ajoutent un fichier que les chapitres jouent avec `<audio>` et `<video>`, à un
chemin relatif au répertoire du paquet. Le type de média est lu d'après les premiers octets quand il n'est pas donné :

| Fichier | Type de média | Type de média de base |
|---|---|---|
| MP3 (en-tête ID3 ou trame MPEG) | `audio/mpeg` | oui |
| AAC dans MP4 (boîte `ftyp`) | `audio/mp4` | oui |
| Opus dans Ogg | `audio/ogg; codecs=opus` | oui |
| Vorbis ou un autre codec dans Ogg | `audio/ogg` | non |
| FLAC | `audio/flac` | non |
| WAV | `audio/wav` | non |
| Vidéo MP4 | `video/mp4` | la vidéo est exemptée |
| WebM | `video/webm` | la vidéo est exemptée |
| Vidéo Ogg | `video/ogg` | la vidéo est exemptée |

Un type de média donné en argument peut nommer des codecs, comme `video/webm; codecs="vp9,opus"`.

```php
$book = $book->withAsset(
    Asset::audio('audio/theme.mp3', $mp3),
    Asset::video('video/trailer.mp4', $mp4),
    Asset::textTrack('video/trailer.en.vtt', $vtt),
)->withChapter(new Chapter(
    '<audio controls="" src="audio/theme.mp3">The theme, played on a fiddle.</audio>'
    .'<video controls="" src="video/trailer.mp4">'
    .'<track kind="captions" src="video/trailer.en.vtt" srclang="en" label="English"/>'
    .'</video>',
));
```

Les chapitres désignent ces fichiers par des chemins relatifs à eux-mêmes, comme ils le font pour les images ; une
adresse que le livre ne contient pas est retirée, et l'audio et la vidéo distants restent distants.
`Asset::textTrack()` prend un fichier WebVTT, qui commence par `WEBVTT`, pour des légendes, des sous-titres, des
descriptions ou des titres de chapitres.

### Solutions de repli

Les systèmes de lecture ne sont tenus de jouer que les types de médias de base d'EPUB. Un fichier audio hors de ceux-ci
a besoin d'une solution de repli : un autre fichier audio, nommé par l'attribut `fallback` de son élément de manifeste,
que le système de lecture joue à sa place. La chaîne de solutions de repli se termine par un type de média de base, ou
la ressource est refusée.

```php
$mp3 = Asset::audio('audio/theme.mp3', $mp3Bytes);
$flac = Asset::audio('audio/theme.flac', $flacBytes, fallback: $mp3);

$book = $book->withAsset($flac);
```

Une solution de repli est écrite avec la ressource qui la nomme ; l'ajouter aussi au livre ne l'écrit qu'une fois. Une
vidéo peut aussi avoir une solution de repli, une autre vidéo, bien qu'EPUB n'en exige aucune. Une solution de repli
est du même genre que sa ressource et à un autre chemin.

## Superpositions de médias

Une superposition de médias synchronise un chapitre avec une narration : chaque `Clip` nomme un élément du chapitre par
son identifiant et la partie d'un fichier audio qui le lit, de son début à sa fin en millisecondes. Le système de
lecture joue les extraits dans l'ordre et surligne chaque élément pendant qu'il est lu.

```php
$book = $book
    ->withAsset(
        Asset::audio('audio/chapter-1.mp3', $mp3),
        Asset::stylesheet('css/book.css', '.reading { background: #ffef9f; }'),
    )
    ->withNarration(new Narration(['Ann Reader'], activeClass: 'reading'))
    ->withChapter(new Chapter(
        '<h1 id="title">The Storm</h1><p id="s1">The light went out at midnight.</p>',
        overlay: new MediaOverlay(
            new Clip('title', 'audio/chapter-1.mp3', 0, 1_800),
            new Clip('s1', 'audio/chapter-1.mp3', 1_800, 4_250),
        ),
    ));
```

Le rédacteur place le document SMIL à côté de son chapitre (`chapter-1.smil` pour `chapter-1.xhtml`), le nomme dans
l'attribut `media-overlay` de l'élément de manifeste du chapitre, et déclare dans le document de paquet la durée de
chaque superposition, leur total, les narrateurs et les classes de `Narration` : `activeClass` est placée sur l'élément
en cours de lecture, `playbackActiveClass` sur le document pendant qu'il est joué. Un livre qui déclare ces classes les
stylise, il lui faut donc une feuille de style. Le paquet reçoit aussi le mode d'accès `auditory` et la fonctionnalité
`synchronizedAudioText`.

Le livre est refusé quand un extrait nomme un élément que son chapitre ne contient pas, lit un fichier qui n'est pas
une ressource audio du livre dans un type de média de base, ou commence après sa fin ; quand une superposition n'a
aucun extrait ; ou quand le chemin d'un document SMIL est pris par un autre fichier.

## Mise en page fixe

Un livre à mise en page fluide laisse le système de lecture ajuster son texte à l'écran. Un livre à mise en page fixe
est mis en page une seule fois : chaque page a sa taille, sa fenêtre d'affichage, et le système de lecture la met à
l'échelle.

```php
$book = $book
    ->withRendition(Rendition::fixed(1200, 1600, Orientation::Portrait, Spread::Landscape))
    ->withChapter(
        new Chapter('<img src="pages/1.png" alt="Page 1: the hero sets out"/>', pageSpread: PageSpread::Right),
        new Chapter('<img src="pages/2.png" alt="Page 2: a storm"/>', pageSpread: PageSpread::Left),
        new Chapter('<h1>Notes</h1><p>…</p>', layout: Layout::Reflowable),
    );
```

`Rendition` déclare `rendition:layout`, `rendition:orientation` et `rendition:spread` dans le document de paquet quand
ils diffèrent des valeurs par défaut : fluide, l'une ou l'autre orientation, double page selon le choix du système de
lecture. Un livre prépaginé a besoin d'une fenêtre d'affichage, la taille de ses pages en pixels CSS ; chaque document
prépaginé reçoit `<meta name="viewport" content="width=…, height=…"/>` dans son en-tête.

Un chapitre peut différer du livre : `layout: Layout::PrePaginated` avec son `viewport` dans un livre fluide, comme une
carte ou une feuille de personnage dans un roman, ou `layout: Layout::Reflowable` dans un livre à mise en page fixe,
comme ses notes. Son `viewport` remplace celui du livre pour cette page. `pageSpread` la place sur la page de gauche,
sur la page de droite, ou sur les deux, centrée. Ces choix sont écrits comme des propriétés de la place du chapitre
dans l'épine. Le document de couverture d'un livre à mise en page fixe prend la fenêtre d'affichage du livre ; son
document de navigation, quand il est dans l'ordre de lecture, reste fluide.

Un chapitre prépaginé sans fenêtre d'affichage, la sienne ou celle du livre, et un chapitre fluide à qui l'on donne une
fenêtre d'affichage, sont refusés.
