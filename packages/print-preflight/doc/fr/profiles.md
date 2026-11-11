<!-- phil-dev source-sha256=d8a02c1e23ab4e26a1b09677fa5465a10be59c394d62fe0e35b3066a63e1b273 -->
# Profils d'imprimeur

Une `PrintProfile` contient ce qu'un imprimeur exige d'un fichier. Chaque argument est nommé et a une valeur par défaut
qui convient à la plupart des imprimeurs offset et numériques ; le profil d'un imprimeur donné ne fixe que ce qui
diffère.

```php
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Size;

$booklet = new PrintProfile(
    trimSizes: [Size::a5()],
    bleed: Length::mm(3),
    safetyMargin: Length::mm(6),
    minimumPages: 8,
    maximumPages: 64,
    pageMultiple: 4,
);
```

## Les règles

| Argument | Par défaut | Règle |
|---|---|---|
| `trimSizes` | `[]` | Les formats rognés que l'imprimeur imprime, chaque orientation acceptée listée à part ; vide pour tout format |
| `uniformTrimSize` | `true` | Chaque page a le format rogné de la première page |
| `bleed` | `Length::mm(3)` | Le fond perdu que chaque page doit porter, et la distance dont un élément qui déborde doit dépasser le bord de coupe |
| `safetyMargin` | `Length::mm(5)` | La distance à l'intérieur du bord de coupe où le contenu doit rester |
| `minimumResolution` | `150` | La résolution effective, en pixels par pouce, sous laquelle une image est une erreur |
| `targetResolution` | `300` | La résolution effective sous laquelle une image est un avertissement |
| `maximumResolution` | `null` | La résolution effective au-dessus de laquelle une image est une remarque, comme poids que l'imprimeur écarte ; aucune par défaut |
| `minimumPages` | `1` | Le moins de pages que l'imprimeur relie |
| `maximumPages` | `null` | Le plus de pages qu'il relie ; aucune limite par défaut |
| `pageMultiple` | `1` | Le nombre de pages doit en être un multiple : 2 pour une feuille imprimée recto verso, 4 pour une piqûre à cheval |
| `colourWorkflow` | `ColourWorkflow::Cmyk` | Les couleurs qu'attend l'imprimeur : CMJN, ou RVB pour les services qui convertissent eux-mêmes |
| `allowSpotColours` | `false` | Si l'imprimeur tire des plaques de tons directs |
| `maximumInkCoverage` | `300` | La couverture d'encre totale, en pour cent, au-dessus de laquelle une image ou un aplat est une erreur, dans un flux CMJN : 300 convient au papier couché sur une presse offset, 260 à 280 au papier non couché, 240 au papier journal |
| `minimumLineWidth` | `Length::pt(0.25)` | Le filet le plus fin que l'imprimeur reproduit ; un filet plus fin est un avertissement |
| `allowOverprint` | `true` | Si l'imprimeur respecte la surimpression ; sinon, chaque élément en surimpression est un avertissement |
| `allowTransparency` | `true` | Si l'imprimeur accepte la transparence active, comme PDF/X-4 ; sinon, comme avec PDF/X-1a, chaque élément qui l'utilise est un avertissement |
| `severities` | `[]` | La gravité d'une règle, par son code, à la place de sa gravité par défaut |
| `ignore` | `[]` | Les codes des règles à ne pas signaler |

Un profil dont les règles se contredisent est refusé par `InvalidProfile` : un fond perdu ou une marge de sécurité
négatifs, une résolution minimale qui n'est pas positive, une cible sous le minimum, un maximum sous la cible, un nombre
minimal de pages sous un, un maximum sous le minimum, un multiple de pages sous un, une couverture d'encre maximale qui
n'est pas positive, une épaisseur minimale de filet négative.

## Les gravités

Chaque règle intégrée a une gravité par défaut (voyez [Vérifications et constats](checks.md)). Un imprimeur qui ajoute
lui-même les pages blanches fait du multiple un avertissement ; un imprimeur qui ne convertit jamais les couleurs fait
du RVB une erreur ; une application qui ne veut pas de remarques sur la couleur les ignore :

```php
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;

$profile = new PrintProfile(
    pageMultiple: 4,
    severities: [Rule::PageCountMultiple->value => Severity::Warning, Rule::ColourRgbInCmyk->value => Severity::Error],
    ignore: [Rule::ColourSpot->value],
);
```

Les clés sont des codes, pas des cas de `Rule` : un profil fixe donc aussi la gravité des constats d'une vérification
qu'ajoute l'application.
