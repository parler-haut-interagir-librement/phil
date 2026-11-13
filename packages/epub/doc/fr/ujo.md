<!-- phil-dev source-sha256=fc00e4c15eaf453aab583d4f1b09add0f95e932f2b075e03ed0b869c5ec5f43d -->
# Documents UJO

UJO (Universal JSON Output) est un format JSON pour des documents faits de blocs ; `phil/ujo` le lit, le vérifie et
en produit le rendu. Avec `phil/ujo` installé, `Ujo\UjoChapters` transforme un document UJO en chapitres :

```php
use Phil\Epub\Ujo\UjoChapters;

$chapters = new UjoChapters()->chapters($json); // or a Phil\Ujo\Document
$book = $book->withChapter(...$chapters);
```

Le document est vérifié et rendu en entier par le moteur de rendu HTML purifié de `phil/ujo`, pour que son bloc `toc`
liste chaque titre et que ses notes de bas de page trouvent leurs références. Il est ensuite coupé à ses blocs
`header` de premier niveau, de niveau `splitLevel` ou plus élevé (1 par défaut), avec `HtmlSplitter`. Les liens de son
bloc `toc` et les notes de bas de page qui pointent désormais vers un autre chapitre sont réécrits par le rédacteur.

## Sauts de page

Le début du document est la page `firstPage` (1 par défaut), et chaque bloc `pageBreak` commence la page suivante :
chacun devient un marqueur de saut de page, listé dans la liste de pages du livre. Un marqueur qui terminerait un
chapitre (un saut de page juste avant un titre) ouvre plutôt le chapitre suivant. Avec `firstPage: null`, les sauts de
page ne laissent rien.

```php
new UjoChapters(splitLevel: 2, firstPage: 5);
```

## Normaliseur et moteur de rendu

`UjoChapters` prend le `Normalizer` et le `HtmlRenderer` de l'application, pour lire ses propres types de blocs ou en
produire le rendu à sa façon ; il ne remplace que le moteur de rendu de `pageBreak`. Les images des documents UJO
pointent d'ordinaire vers les adresses de médias de l'application : ajoutez les images comme ressources et associez
leurs adresses avec `Book::withUrls()`, sinon elles sont remplacées par leurs légendes. Les intégrations, qui chargent
une page distante, sont laissées de côté.
