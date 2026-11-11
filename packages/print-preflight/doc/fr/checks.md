<!-- phil-dev source-sha256=f0a7cc38ab549d4c9fe2d060b761a8552162be951875c025cf6f37576bf3d2bb -->
# Vérifications et constats

`Preflight::check()` exécute chaque vérification sur le document, écarte les constats que le profil ignore, donne aux
autres la gravité que fixe le profil, et renvoie un `Report`.

## Les gravités

| Gravité | Sens |
|---|---|
| `Severity::Error` | L'imprimeur refuserait le fichier, ou l'imprimerait mal : il faut le corriger d'abord |
| `Severity::Warning` | Le fichier s'imprime, mais probablement pas comme prévu : quelqu'un devrait regarder |
| `Severity::Notice` | Bon à savoir, rien à corriger : une conversion que fera l'imprimeur |

## Les vérifications intégrées et leurs règles

Les vérifications s'exécutent dans cet ordre, qui est aussi l'ordre de leurs constats dans une page.

| Vérification | Code (`Rule`) | Par défaut | Signalé quand |
|---|---|---|---|
| `PageCount` | `page-count.empty` | erreur | Le document n'a aucune page ; les autres règles du nombre de pages ne sont alors pas vérifiées |
| | `page-count.below-minimum` | erreur | Moins de pages que `minimumPages` |
| | `page-count.above-maximum` | erreur | Plus de pages que `maximumPages` |
| | `page-count.multiple` | erreur | Un nombre qui n'est pas un multiple de `pageMultiple` ; le constat dit combien de pages blanches ajouter |
| `PageGeometry` | `page-size.not-accepted` | erreur | Une page dont le format rogné ne fait pas partie de `trimSizes` |
| | `page-size.mixed` | erreur | Une page dont le format rogné diffère de celui de la première page, quand `uniformTrimSize` est vrai et que le format est accepté |
| | `bleed.too-small` | erreur | Une page qui porte moins de fond perdu que `bleed` |
| `Fonts` | `font.not-embedded` | erreur | Une police que le fichier n'intègre pas ; ses permissions ne sont alors pas vérifiées |
| | `font.restricted` | erreur | Une police dont l'usage pour l'intégration est restreint |
| | `font.bitmap-only` | erreur | Une police qui ne peut être intégrée qu'en bitmap |
| | `font.subset-forbidden` | erreur | Une police intégrée en sous-ensemble dont les permissions interdisent les sous-ensembles |
| `ImageResolution` | `image.below-minimum` | erreur | Une image sous `minimumResolution` |
| | `image.below-target` | avertissement | Une image sous `targetResolution` |
| | `image.above-maximum` | remarque | Une image au-dessus de `maximumResolution`, quand il y en a un |
| `Colours` | `colour.rgb-in-cmyk` | remarque | Une image ou une boîte RVB dans un flux CMJN |
| | `colour.cmyk-in-rgb` | remarque | Une image ou une boîte CMJN dans un flux RVB |
| | `colour.spot` | avertissement | Un ton direct quand l'imprimeur n'en tire pas |
| `InkCoverage` | `ink.image-above-maximum` | erreur | Une image dont l'`inkCoverage` dépasse `maximumInkCoverage`, dans un flux CMJN |
| | `ink.flat-above-maximum` | erreur | Une boîte dont l'`inkCoverage` dépasse `maximumInkCoverage`, dans un flux CMJN |
| `Overprint` | `overprint.white` | erreur | Une boîte en surimpression dont l'`inkCoverage` est sous 0,5 %, quand `allowOverprint` est vrai : elle ne dépose aucune encre et disparaît |
| | `overprint.not-allowed` | avertissement | Une boîte en surimpression quand `allowOverprint` est faux : l'imprimeur la défonce plutôt |
| `Transparency` | `transparency.not-allowed` | avertissement | Une image ou une boîte qui utilise la transparence quand `allowTransparency` est faux |
| `Hairlines` | `line.hairline` | avertissement | Une boîte dont le `lineWidth` est sous `minimumLineWidth` |
| `Placement` | `placement.outside-trim` | avertissement | Un élément entièrement hors du format rogné, qui ne se voit jamais |
| | `placement.crosses-trim` | erreur | Du contenu qui franchit le bord de coupe |
| | `placement.in-safety-margin` | avertissement | Du contenu dans la marge de sécurité |
| | `placement.short-of-bleed` | erreur | Un élément qui déborde et touche le bord de coupe sans le dépasser de `bleed` |

Les bords sont comparés à la tolérance d'un demi-point de `Length` près : un élément qui se termine à moins d'un
demi-point du bord de coupe le touche, et un élément qui le dépasse d'au plus un demi-point ne le franchit pas. Les
épaisseurs de filet sont comparées exactement : un demi-point est plus épais que la plupart des minimums.

Un élément dont le modèle omet la couverture d'encre ou l'épaisseur de filet n'est pas vérifié sur ce point. Dans un
flux RVB, la couverture d'encre n'est pas vérifiée : l'imprimeur sépare les couleurs et limite l'encre lui-même.

## Les constats

Un `Finding` contient :

| Propriété | Contenu |
|---|---|
| `severity` | Sa `Severity`, après le profil |
| `code` | Le code de la règle, comme `image.below-target` |
| `message` | Une phrase en anglais qui nomme l'élément, la page et les valeurs : `The image "cover" on page 2 prints at 149 ppi, below the 150 ppi minimum.` |
| `page` | La page où il se trouve, numérotée à partir de 1 ; `null` pour le document entier |
| `subject` | L'identifiant de l'élément ou le nom de la police qu'il concerne, quand il y en a un |
| `parameters` | Les valeurs que cite la phrase, par nom, pour une application qui écrit ses propres messages |

Les paramètres de chaque code :

| Code | Paramètres |
|---|---|
| `page-count.below-minimum` | `count`, `minimum` |
| `page-count.above-maximum` | `count`, `maximum` |
| `page-count.multiple` | `count`, `multiple`, `missing` |
| `page-size.not-accepted` | `size` |
| `page-size.mixed` | `size`, `first` |
| `bleed.too-small` | `bleed`, `required` |
| `image.below-minimum` | `resolution`, `minimum` |
| `image.below-target` | `resolution`, `target` |
| `image.above-maximum` | `resolution`, `maximum` |
| `placement.crosses-trim` | `edges` |
| `placement.in-safety-margin` | `edges`, `margin` |
| `placement.short-of-bleed` | `edges`, `bleed` |
| `ink.image-above-maximum` | `coverage`, `maximum` |
| `ink.flat-above-maximum` | `coverage`, `maximum` |
| `line.hairline` | `width`, `minimum` |

Les formats et les longueurs s'écrivent en millimètres à une décimale (`3 mm`, `148 × 210 mm`), les épaisseurs de
filet en points à deux décimales (`0.25 pt`), les résolutions en pixels par pouce entiers arrondis vers le bas, pour que
299,6 ppi ne se lise jamais comme la cible de 300 ppi, et les couvertures d'encre en pour cent entiers arrondis vers le
haut, pour que 300,2 % ne se lise jamais comme le maximum de 300 %. Les bords sont listés haut, droite, bas, gauche.

## Le rapport

`Report` liste d'abord les constats sur le document entier, puis page par page, chaque page dans l'ordre des
vérifications.

| Méthode | Renvoie |
|---|---|
| `passes()` | Si aucun constat n'est une erreur : le fichier peut aller à l'imprimeur |
| `isClean()` | S'il n'y a ni erreur ni avertissement |
| `errors()`, `warnings()`, `notices()` | Les constats de cette gravité |
| `forPage(?int $page)` | Les constats d'une page, ou ceux sur le document entier avec `null` |
| `count()` | Le nombre de constats |
| `toArray()` | Chaque constat sous forme de tableau, prêt pour JSON |

## Ajouter une vérification

Une vérification implémente `Check` et renvoie ses constats à leur propre gravité ; les gravités et les codes ignorés
du profil s'y appliquent comme aux vérifications intégrées :

```php
use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Severity;

final readonly class SpineWidth implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        return \count($document->pages) > 48
            ? [new Finding(Severity::Warning, 'app.spine', 'The spine is wide enough for a title; add one.')]
            : [];
    }
}

$preflight = new Preflight($profile)->withCheck(new SpineWidth());
```

`new Preflight($profile, [...])` remplace les vérifications intégrées par la liste donnée ; `Preflight::defaultChecks()`
les renvoie, pour en garder certaines.
