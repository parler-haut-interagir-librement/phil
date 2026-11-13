<!-- phil-dev source-sha256=ab158e147d8b8dee1ce0266570bb2bbcccbdd795a186e1083f8895561b68baeb -->
# Moteurs HTML et CSS

La bibliothèque ne met pas en page de HTML ni de CSS. Un moteur de média paginé le fait : il lit un document et ses
feuilles de style, et les découpe en pages avec des en-têtes courants, des compteurs de pages, des notes de bas de page
et des colonnes. `phil/paged-media-bundle` déclare le port que ce moteur implémente, `PrintEngine`, et
`phil/paged-media-weasyprint` l'implémente avec WeasyPrint.

Ce que la bibliothèque donne à un tel moteur, c'est la page sur laquelle le document est imprimé.

## La règle de page

`Phil\PagedMedia\Css\PageRule` écrit la règle `@page` de CSS Paged Media : le format de la page rognée, ses marges, le
fond perdu et les marques d'imprimeur.

```php
use Phil\PagedMedia\Css\PageRule;
use Phil\PagedMedia\Css\PrinterMarks;
use Phil\PagedMedia\Pdf\PageSize;

$rule = new PageRule(
    PageSize::ofMillimetres(148, 210),   // the page once trimmed
    [54, 45],                            // margins, as the CSS shorthand writes them
    8.5,                                 // bleed: 3 mm
    PrinterMarks::Crop,
);

$rule->css();   // "@page { size: 419.53pt 595.28pt; margin: 54pt 45pt; bleed: 8.5pt; marks: crop }"
```

Chaque mesure est en points, comme pour le moteur d'écriture natif : un `PageSize` désigne donc la même page dans les
deux. La règle est une feuille de style à part, placée avant celle du document afin qu'une page nommée ou une page
`:first` du document puisse la modifier.

| Argument | Accepté | Écrit |
|---|---|---|
| `$size` | Un `PageSize` | `size`, toujours |
| `$margins` | Une liste de une à quatre marges, chacune d'au moins 0 et de moins de la moitié du plus court côté | `margin`, quand il y en a une ; la valeur par défaut du moteur sinon |
| `$bleed` | De 0 à `PageRule::MAXIMUM_BLEED`, un pouce | `bleed`, quand il dépasse 0 |
| `$marks` | `PrinterMarks::None`, `Crop`, `Cross` ou `CropAndCross` | `marks`, sauf `None` |

Une valeur hors limites lève `InvalidDocument`. La même règle donne le même texte : une feuille de style peut donc être
mise en cache ou comparée.

Le moteur trace le fond perdu et les marques hors de la page rognée et enregistre les deux dans le PDF : la boîte de
média prend le fond perdu, la boîte de rognage garde la page. CSS donne à une page avec des marques de coupe et sans
fond perdu un fond perdu de 6 points, pour que les marques aient la place voulue.

## Ce qu'ajoute un moteur

L'intention de sortie, PDF/X et son profil ICC, PDF/A et PDF/UA, les balises, le confinement du moteur et ses limites
reviennent à l'adaptateur du moteur : voir `phil/paged-media-weasyprint`. Un PDF écrit par un moteur passe ensuite par
le client Gotenberg comme n'importe quel autre, pour être fusionné, estampillé d'un filigrane (`StampText`,
`StampPdf`), signé ou horodaté.

## Non couvert

| Besoin | État |
|---|---|
| D'autres moteurs derrière `PrintEngine`, comme Prince ou WeasyPrint exécuté en service HTTP | Pas commencé ; chacun serait un paquet à part |
| Une limite de pages imprimées par un moteur | Pas commencé ; le délai du moteur borne un document coûteux |
| Des formules dessinées en SVG pour un moteur sans MathML | Pas commencé : WeasyPrint ne lit pas MathML et imprime le texte de repli d'une formule |
