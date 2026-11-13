<!-- phil-dev source-sha256=fec796b99b601c63e67f2eb59d5b28af04e63045acb8a9e8af1bc79502d83d96 -->
# Navigation

Chaque livre a un document de navigation, `nav.xhtml`, avec sa table des matières, ses repères et, quand le contenu a
des marqueurs de saut de page, sa liste de pages. Un livre peut aussi porter la table des matières EPUB 2, `toc.ncx`, pour les anciens systèmes de
lecture.

## Table des matières

Par défaut, la table des matières suit les titres des chapitres qui sont `inToc`, de `<h1>` jusqu'à la profondeur du
livre (`Book::withTocDepth()`, 3 par défaut) : chaque titre est une entrée imbriquée sous le titre précédent de niveau
plus élevé. Un titre sans identifiant en reçoit un (`heading-1`, `heading-2`…). Un chapitre sans titre à cette
profondeur forme une seule entrée, son titre, au niveau du titre le plus élevé du livre.

Les titres dans `aside`, `nav`, `blockquote`, `figure`, `details` et `table`, les titres avec `data-toc="exclude"` et
les titres vides en restent exclus.

`Book::withToc()` donne plutôt la table, sous forme de valeurs `TocEntry` dont les adresses sont relatives au
répertoire du paquet :

```php
$book = $book->withToc([
    new TocEntry('Rules', 'chapter-1.xhtml', [
        new TocEntry('Combat', 'chapter-1.xhtml#combat'),
    ]),
    new TocEntry('Appendix', 'appendix.xhtml'),
]);
```

## Repères

Les repères listent la couverture, quand il y en a une ; la table des matières, quand le document de navigation est
dans l'ordre de lecture (`Book::withNavigationInSpine()`) ; chaque chapitre doté d'un `LandmarkType`, avec son titre
pour libellé ; et le début du contenu, soit le chapitre marqué `LandmarkType::BodyMatter`, soit le premier chapitre
sans repère.

## Liste de pages

Un livre qui suit une édition imprimée marque où commence chaque page imprimée, pour qu'un lecteur puisse aller à une
page donnée par quelqu'un qui tient l'imprimé. La bibliothèque liste chaque élément d'un chapitre qui a
`role="doc-pagebreak"` ou l'`epub:type` `pagebreak`, avec pour libellé son `aria-label`, son `title` ou son texte :

```html
<span role="doc-pagebreak" epub:type="pagebreak" aria-label="42"></span>
```

Un élément sans identifiant en reçoit un (`page-1`…). Un marqueur sans libellé n'est pas listé. `Metadata::$source`
nomme l'édition imprimée. Les documents UJO tirent leurs marqueurs de leurs sauts de page : voir
[Documents UJO](ujo.md).

## Table des matières EPUB 2

Les systèmes de lecture conçus pour EPUB 2, comme certaines liseuses anciennes et certaines applications de
bibliothèque, ne lisent pas le document de navigation. `Book::withNcx()` leur ajoute `toc.ncx`, déclaré dans le
manifeste et nommé par l'attribut `toc` de l'épine :

```php
$book = $book->withNcx();
```

Elle contient les mêmes entrées que la table des matières, imbriquées de la même façon, et la même liste de pages : une
page libellée en chiffres est une page `normal` avec son numéro, une page libellée en chiffres romains une page
`front`, toute autre une page `special`. Les entrées qui mènent à la même adresse partagent leur ordre de lecture. Son
identifiant unique est celui du livre, si bien que les deux tables décrivent une seule publication. `withNcx(false)`
la retire de nouveau.

## Libellés

`NavigationLabels` contient les mots du document de navigation, en anglais par défaut ; donnez-les dans la langue du
livre :

```php
$book = $book->withLabels(new NavigationLabels(
    contents: 'Table des matières',
    landmarks: 'Repères',
    pages: 'Pages',
    cover: 'Couverture',
    tableOfContents: 'Table des matières',
    start: 'Début du texte',
));
```
