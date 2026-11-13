<!-- phil-dev source-sha256=3c57b968b41da2bd114d0fed4fdf6aba4eb73d1609acfe09d1466dd67058386d -->
# Gabarits d'imprimés

La bibliothèque dessine à partir de données des imprimés courants avec le moteur d'écriture natif : des porte-noms, des
étiquettes d'adresse, de produit et de tablette, des cartes-cadeaux, des cartes de téléchargement, des calendriers et des
éditions en gros caractères. Chaque gabarit est une classe construite avec ses réglages, dont `render()` prend une liste
d'éléments et renvoie un `Document`, sur lequel l'application peut encore dessiner avant de l'écrire.

```php
use Phil\PagedMedia\Template\Badge;
use Phil\PagedMedia\Template\NameBadges;

$document = new NameBadges('Spring Convention 2027')->render([
    new Badge('Élise Tremblay', 'she/her', 'The Gnomes'),
    new Badge('Sam Okafor', organisation: 'Dice Club'),
]);

file_put_contents('badges.pdf', $document->render());
```

Les gabarits impriment les valeurs telles qu'on les donne : un prix, un montant ou une date est formaté par
l'application, dans la langue et la devise du lecteur, tout comme les mots autour des valeurs — le « Code » et le
« Valable jusqu'au » d'une carte-cadeau, les noms des mois et des jours d'un calendrier. Un code à barres ou un code QR
est une `Image` que l'application dessine et transmet ; la bibliothèque ne les encode pas.

## Planches

Les étiquettes et les cartes s'impriment sur des planches, décrites par une `LabelSheet` : la page, les colonnes et les
rangées, la taille de chaque étiquette, les marges depuis le coin supérieur gauche et les espaces entre les étiquettes,
en points. Les éléments remplissent la planche rangée par rangée à partir de l'étiquette en haut à gauche, et une
nouvelle page commence quand une planche est pleine.

| Planche | Ce que c'est |
|---|---|
| `LabelSheet::letter30()` | Trente étiquettes d'adresse de 2⅝ × 1 pouce sur Letter, trois colonnes de dix |
| `LabelSheet::letter10()` | Dix cartes de 3½ × 2 pouces sur Letter, deux colonnes de cinq : du stock de cartes professionnelles |
| `LabelSheet::letter6()` | Six porte-noms de 4 × 3 pouces sur Letter, deux colonnes de trois |
| `LabelSheet::a4x21()` | Vingt et une étiquettes de 70 × 42,3 mm sur A4, trois colonnes de sept |
| `LabelSheet::a4x8()` | Huit cartes de 105 × 74 mm sur A4, deux colonnes de quatre |
| `LabelSheet::single($page)` | Une étiquette qui remplit une page de sa propre taille, comme du carton coupé à la taille |
| `LabelSheet::of(...)` | Toute autre grille ; les étiquettes qui ne tiennent pas sur la page sont refusées |

## Polices, métadonnées et conformité

Chaque gabarit prend des `TemplateFonts` — une police normale et une police grasse, Helvetica et Helvetica Bold par
défaut —, puis les `Metadata` du document et une `Conformance` facultative. Un document conforme à PDF/A-2u a besoin de
polices TrueType, qui doivent aussi contenir les points de suspension (…) qui terminent un texte coupé pour tenir.

Une ligne de texte est réduite, par demi-points jusqu'à un plancher, jusqu'à ce qu'elle tienne dans sa largeur, puis
coupée avec des points de suspension ; un paragraphe est renvoyé à la ligne selon sa largeur et coupé après les lignes
que sa place contient.

## Les gabarits

| Gabarit | Éléments | Ce qu'il imprime |
|---|---|---|
| `NameBadges($event, $sheet, $fonts, $accent, $logo)` | `Badge($name, $subtitle, $organisation)` | Un bandeau avec le nom et le logo de l'événement, le nom aussi grand que le porte-nom le permet, puis un sous-titre — des pronoms, un rôle — et une organisation ; `letter6()` par défaut |
| `AddressLabels($sheet, $fonts, $size)` | `AddressLabel($lines)` | Les lignes centrées dans l'étiquette, la première en gras, les lignes vides omises, autant que l'étiquette en contient ; `letter30()` par défaut |
| `ProductLabels($sheet, $fonts)` | `ProductLabel($name, $price, $sku, $detail, $code)` | Le nom sur deux lignes au plus, un détail, le prix en gras avec l'UGS, l'image du code à droite ; `letter30()` par défaut |
| `ShelfLabels($sheet, $fonts)` | `ShelfLabel($name, $price, $unitPrice, $sku, $location, $code)` | Le nom en haut, le prix en grand à droite avec le prix unitaire dessous, l'UGS et l'emplacement au pied, l'image du code en bas à gauche ; `letter10()` par défaut |
| `GiftCards($title, $sheet, $fonts, $logo, $codeLabel, $pinLabel, $validUntilLabel, $cutLines)` | `GiftCard($amount, $code, $pin, $recipient, $message, $validUntil, $codeImage)` | Le titre et le logo, le montant en grand, le destinataire et le message, le code, le NIP et la date de validité au pied, l'image du code à droite, un trait de coupe fin ; `letter10()` par défaut |
| `DownloadCards($instructions, $address, $sheet, $fonts, $logo, $cutLines)` | `DownloadCard($title, $code, $subtitle, $codeImage)` | Une carte par code : le titre et le sous-titre, comment et où l'échanger, le code dans un encadré, l'image à numériser ; `letter10()` par défaut |
| `Calendar($weekdays, $firstDay, $page, $fonts, $accent)` | `CalendarMonth($year, $month, $title, $events)` | Un mois par page, en Letter à l'horizontale par défaut : le titre, les noms des jours, les semaines à partir du premier jour demandé (1 pour lundi à 7 pour dimanche), chaque jour avec son numéro et ses événements, coupés à ce que montre sa case |
| `LargePrint($page, $fonts, $size, $headingSize, $margin, $pageNumbers)` | `LargePrintBlock::heading()`, `LargePrintBlock::paragraph()` | 18 points ou plus, aligné à gauche, un interligne d'une fois et demie, de larges marges, les titres gardés avec les deux lignes qui suivent, le numéro de page au pied |

Une carte-cadeau ou une carte de téléchargement sur une planche dont la carte est trop petite pour tout ce qu'elle
contient garde son pied — le code d'abord — et omet le destinataire et le message plutôt que de les imprimer par-dessus.

## Vérifications

Chaque élément se vérifie à sa construction : un porte-nom a un nom, une adresse une ligne, une étiquette de produit ou
de tablette un nom et un prix, une carte-cadeau un montant et un code, une carte de téléchargement un titre et un code ;
un mois de calendrier est un vrai mois dont les événements tombent sur ses jours ; un calendrier nomme sept jours et
commence ses semaines par l'un d'eux ; les gros caractères font 18 points ou plus, avec des titres pas plus petits, et
des marges de 18 points ou plus qui laissent de la place au texte. Chaque refus est une `InvalidDocument`.

## Dans Symfony

`phil/paged-media-bundle` enregistre chaque gabarit comme un gabarit de document, rendu à partir de paramètres par
`PagedMedia` ou dans un *worker* par un message, si bien que les codes d'une carte-cadeau ou d'une carte de
téléchargement transitent par le magasin des tâches, jamais par la file d'attente.
