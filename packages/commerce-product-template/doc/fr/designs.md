<!-- phil-dev source-sha256=36fcd415125ede175b51fc37469b580ed8f022d4e0d21a61c239ae8cb6369769 -->
# Visuels

Un visuel est ce qui est imprimé sur un gabarit, indépendamment de l'article vierge : son code, première partie de ses
UGS (unités de gestion de stock), son nom, les langues dans lesquelles il existe, ses illustrations, les gabarits sur
lesquels il est fabriqué et les valeurs qu'il permet.

```php
$gnome = Design::of('NAQ001', Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
    [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')],
    [
        Artwork::of('front', 'art/naq001/front-fr.png', language: 'F'),
        Artwork::of('front', 'art/naq001/front-en.png', language: 'E'),
        Artwork::of('front', 'art/naq001/front-convention.png', when: ['version' => ['C']]),
        Artwork::of('back', 'art/naq001/back.png', 'full'),
    ],
    templates: ['unisex', 'fitted'],
    allowed: ['colour' => ['BK', 'NV', 'CH'], 'version' => ['O', 'C']],
);
```

## Versions linguistiques

Une `LanguageVersion` est le code que l'UGS écrit pour la langue, de 1 à 8 caractères, et la locale dans laquelle sont
nommés les produits de cette version. Un visuel en a jusqu'à 8 ; un visuel qui n'en a aucune est planifié une seule
fois, son `{lang}` étant vide.

## Illustrations

Une `Artwork` est une image imprimée dans une zone d'une face : le fichier d'impression couleur de cette zone, à ses
proportions.

| Partie | Règle |
|---|---|
| Face | Le code d'une face du gabarit ; sur un gabarit sans cette face, l'illustration est laissée de côté |
| Zone | Une zone de cette face ; sa première zone quand elle vaut `null` ; une zone absente de la face fait refuser le plan |
| Langue | Le code de l'une des versions linguistiques du visuel ; toutes les versions quand elle vaut `null` |
| Condition | Des codes de valeurs par axe : les combinaisons auxquelles elle s'applique ; toutes les combinaisons quand elle est vide |

Sur chaque zone de chaque produit, l'illustration applicable **la plus spécifique** est imprimée. Une illustration d'une
seule langue est plus spécifique que toute illustration de toutes les langues ; ensuite, plus sa condition nomme d'axes,
plus elle est spécifique. Deux illustrations qui s'appliquent également font refuser le plan avec
`PlanRefused::ambiguousArtwork()` ; deux illustrations de mêmes face, zone, langue et condition font refuser le visuel
avec `InvalidDefinition`. Un visuel a jusqu'à 200 illustrations.

## Ce que permet un visuel

`templates` liste les gabarits sur lesquels il est fabriqué, tous les gabarits quand la liste est vide ; le planifier
sur un autre est refusé avec `PlanRefused::templateNotAllowed()`. `allowed` liste, par axe, les valeurs dans lesquelles
le visuel est fabriqué — les couleurs sur lesquelles il rend bien, les versions qu'il a — ; un axe qu'il ne nomme pas
permet toutes les valeurs, tout comme un gabarit qui n'a pas cet axe.
