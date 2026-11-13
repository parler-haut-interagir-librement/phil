<!-- phil-dev source-sha256=5cca29777fb1214a078148a904875f9c268e23d7fe8e83363ccccc31a5f8f95f -->
# Documents

## Pages

Un `Document` est une liste de pages. Chaque page a une taille, un `PageSize` en points — un soixante-douzième de
pouce :

```php
$pdf = new Document(PageSize::letter());   // every page is Letter unless addPage() says otherwise
$pdf->addPage();
$pdf->addPage(PageSize::a4()->landscape());
```

`PageSize::of($width, $height)` accepte des points, `PageSize::ofMillimetres()` des millimètres ; un côté mesure entre
1 et 14 400 points, la plus grande taille que les lecteurs PDF sont tenus d'afficher. `letter()`, `a4()` et `a5()` sont
prêts à l'emploi.

Les coordonnées partent du coin inférieur gauche de la page, et le dessin se fait sur la dernière page ajoutée :
dessiner avant toute page lève `InvalidDocument`.

## Texte

```php
$pdf->text(54, 700, 'Receipt R-001', 14, StandardFont::HelveticaBold);
$pdf->textRight(558, 680, '12.50', 10, StandardFont::Helvetica, Color::gray(80));
```

`text()` place le début de la ligne de base en (x, y) ; `textRight()` termine le texte en x, pour les montants et les
numéros de page. La taille est en points et supérieure à zéro. Les sauts de ligne d'un texte sont imprimés comme des
espaces : un appel fait une ligne, et `Document::wrap()` découpe un long texte en lignes ([Polices](fonts.md)).

## Lignes et rectangles

```php
$pdf->line(54, 660, 558, 660, 0.5);
$pdf->rectangle(54, 600, 200, 40, fill: Color::hex('#f2f2f2'));
$pdf->rectangle(54, 600, 200, 40, Color::hex('#f2f2f2'), Color::black(), 0.25);
```

Un rectangle est rempli, tracé, ou les deux ; sans aucune des deux couleurs, il est tracé en noir. Les couleurs sont
`Color::gray()`, `Color::rgb()` ou `Color::hex()`, avec des composantes de 0 à 255.

## Images

`image($image, $x, $y, $width, $height)` place une `Image` avec son coin inférieur gauche en (x, y), étirée à la
largeur et à la hauteur données ([Images](images.md)). Une image placée plusieurs fois n'est écrite qu'une fois.

## Champs de formulaire

`field($field)` place sur la dernière page une zone de texte, une case à cocher, un groupe de boutons radio ou un choix,
avec son apparence dessinée. `render()` garde les champs remplissables ; `render(flattenForm: true)` les dessine dans
leurs pages. Voyez [Formulaires](forms.md).

## Métadonnées

```php
new Document(PageSize::a4(), new Metadata(title: 'Facture F-001', author: 'Accounting'));
```

Le titre, l'auteur, le sujet, les mots-clés et le créateur vont dans le dictionnaire d'information, avec
`phil/paged-media` comme producteur ; le texte hors de l'ASCII imprimable est écrit en UTF-16.

## Déterminisme

Les mêmes appels donnent les mêmes octets : aucune date sauf si `Metadata` en reçoit une, aucun identifiant aléatoire,
polices et images dans l'ordre de leur première utilisation. Les tests peuvent comparer un document à un fichier de
référence.

## Compression

Les flux de contenu et les polices intégrées sont compressés avec zlib, tout comme les apparences des champs de
formulaire à partir de 256 octets. `new Document(compress: false)` les écrit tels quels, ce qui rend un document lisible
dans un éditeur de texte pendant le débogage.
