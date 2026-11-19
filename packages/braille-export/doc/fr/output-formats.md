<!-- phil-dev source-sha256=9ea42c192440c1625a8c3abfae8728b8b9709fe4c531b5906d90cb9969240c85 -->
# Formats de sortie

| Écrivain | Format | Pour |
|---|---|---|
| `BrfWriter` | BRF : braille ASCII nord-américain | Les imprimantes braille et les bloc-notes braille |
| `PefWriter` | PEF 2008-1 : le Portable Embosser Format du DAISY Consortium | Les logiciels d'embossage, les archives, l'échange |
| `UnicodeWriter` | Texte en braille Unicode | Un aperçu à l'écran |

## BRF

```php
$brf = new BrfWriter()->write($book);
```

Chaque cellule est le caractère ASCII imprimable que le braille ASCII nord-américain donne à ses points — `A` pour le
point 1, `,` pour le point 6, `#` pour les points 3-4-5-6 —, chaque rangée se termine par un retour de chariot et un
saut de ligne, chaque page par un saut de page. Le fichier est en ASCII, envoyé tel quel à une imprimante braille ; le
format de page du livre est celui sur lequel l'imprimante doit être réglée.

Le BRF a six points : une cellule avec le point 7 ou 8 lance `UnrepresentableCell`, qui nomme la page et la rangée.

## PEF

```php
use Phil\BrailleExport\Output\PefMetadata;

$pef = new PefWriter()->write($book, new PefMetadata(
    identifier: 'urn:isbn:9782070612758',
    creator: 'Antoine de Saint-Exupéry',
    publisher: 'The publisher',
    date: new DateTimeImmutable('2026-11-13'),
));
```

Le fichier garde la structure du livre : un élément `volume` par volume, avec les cellules par ligne, les lignes par
page et s'il est duplex, un `section` par section, un `page` par page, un `row` par rangée en braille Unicode. Ses
métadonnées Dublin Core contiennent le format, `application/x-pef+xml`, l'identifiant, le titre et la langue du livre,
et l'auteur, l'éditeur et la date quand ils sont donnés.

Sans identifiant, le fichier prend `urn:sha256:` et le SHA-256 de son corps, pour qu'un même livre reçoive toujours le
même. Les textes des métadonnées sont échappés, et les caractères que XML 1.0 ne peut pas contenir sont omis.

## Braille Unicode

```php
$pages = new UnicodeWriter()->pages($book);        // each page, its rows separated by line feeds
$text = new UnicodeWriter()->write($book, "\f");   // the whole book, pages separated as asked
```

Les rangées sont les caractères U+2800 à U+28FF ; une police avec des motifs braille les montre en points. Les pages
sont séparées par une ligne blanche, sauf si un autre séparateur est donné.
