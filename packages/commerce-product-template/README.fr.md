<!-- phil-dev source-sha256=203437196af985ff76053023041dfd39f009414d12a6aa9f6c5b59421ac4bad1 -->
# phil/commerce-product-template

**Un gabarit et un visuel donnent chaque produit d'un catalogue : chaque UGS, son prix, ses canaux et ses
illustrations.**

phil/commerce-product-template décrit un produit fabriqué sur commande à partir d'un article vierge. Un **gabarit** est
l'article vierge : ses axes — une taille, une couleur, une version — avec leurs valeurs dans l'ordre, chacune avec son
code, son libellé en plusieurs langues, sa pastille, le nom que lui donne le fournisseur et la photo de l'article vierge
sur chaque face ; le prix de chaque combinaison, à partir d'une base, d'un prix par valeur d'un axe et de suppléments
sur n'importe quel axe ; les faces et leurs zones d'impression en millimètres ; les combinaisons que le fournisseur ne
fabrique pas ; et le patron de l'UGS (unité de gestion de stock), `{design}{lang}{version}{size}{colour}` ou tout autre.
Un **visuel** est ce qui est imprimé : son code, ses versions linguistiques, ses illustrations par face et par zone, les
gabarits sur lesquels il est fabriqué et les valeurs qu'il permet. Le **planificateur** transforme un visuel sur un
gabarit en un produit par langue et par combinaison, refuse un patron qui donnerait la même UGS à deux produits, et
donne le même plan pour les mêmes entrées : un catalogue régénéré à partir de lui est donc mis à jour plutôt que
dupliqué.

Il s'adresse à une boutique qui vend des articles imprimés en de nombreuses tailles et couleurs à partir d'une poignée
d'articles vierges : chandails, kangourous, tasses, affiches. Un chandail est l'exemple que suit la documentation ; rien
dans la bibliothèque ne sait ce qu'est un chandail.

C'est une bibliothèque PHP simple avec `phil/money` pour ses prix et rien d'autre. Le bundle Symfony
`phil/commerce-product-template-bundle` stocke les gabarits et les visuels, génère les produits d'un catalogue à partir
du plan, compose leurs maquettes et déclare leurs options.

## État

| Partie | État |
|---|---|
| Axes et valeurs : codes, libellés par locale, pastilles, noms du fournisseur, photos par face, canaux | Implémenté et testé |
| Prix : base, prix par valeur d'un axe, suppléments sur n'importe quel axe | Implémenté et testé |
| Faces et zones d'impression en millimètres, résolution, rectangle sur les photos | Implémenté et testé |
| Combinaisons exclues par valeurs d'axe | Implémenté et testé |
| Patrons d'UGS, collés ou séparés, avec la vérification des collisions | Implémenté et testé |
| Visuels : versions linguistiques, illustrations par face, zone, langue et condition, gabarits et valeurs permis | Implémenté et testé |
| Planification : produits, prix, canaux, illustrations, noms et attributs | Implémenté et testé |
| Tableaux pour le stockage, versionnés et vérifiés à la lecture | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Gabarits](doc/fr/templates.md) | `Template`, `Axis`, `AxisValue`, `Label`, `Swatch`, `Face`, `PrintZone`, `PhotoBox`, `Pricing`, `Exclusion`. |
| [Patrons d'UGS](doc/fr/sku-patterns.md) | Les marqueurs, les littéraux, la vérification des collisions. |
| [Visuels](doc/fr/designs.md) | `Design`, `LanguageVersion`, `Artwork` : les langues, les illustrations et ce que permet un visuel. |
| [Planification](doc/fr/planning.md) | `VariantPlanner`, `Variant`, `PlacedArtwork` : ce que contient et refuse un plan. |
| [Stockage](doc/fr/storage.md) | `TemplateArray` et `DesignArray` : les tableaux, leur version, ce que refuse la lecture. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que choisit une application. |
| [Exceptions](doc/fr/exceptions.md) | `ProductTemplateException`, `InvalidDefinition`, `PlanRefused`. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, les cibles de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public de la bibliothèque. |

## Démarrage rapide

```sh
composer require phil/commerce-product-template
```

```php
use Phil\CommerceProductTemplate\{Artwork, Axis, AxisValue, Design, Exclusion, Face, Label, LanguageVersion};
use Phil\CommerceProductTemplate\{Pricing, PrintZone, SkuPattern, Swatch, Template, VariantPlanner};
use Phil\Money\{Currency, Money};

$cad = static fn (int $cents): Money => Money::of($cents, Currency::of('CAD'));

$shirt = Template::of('unisex', Label::of(['en' => 'Unisex shirt', 'fr' => 'T-shirt unisexe']),
    SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
    Pricing::of($cad(2_500))->withPrice('size', '2X', $cad(2_800))->withSupplement('version', 'C', $cad(1_000)),
    [
        Axis::of('version', Label::of(['en' => 'Version']),
            AxisValue::of('O', Label::of(['en' => 'Regular'])),
            AxisValue::of('C', Label::of(['en' => 'Convention']), channels: ['convention'])),
        Axis::of('size', Label::of(['en' => 'Size']),
            AxisValue::of('M', Label::of(['en' => 'M'])), AxisValue::of('2X', Label::of(['en' => '2XL']))),
        Axis::of('colour', Label::of(['en' => 'Colour']),
            AxisValue::of('BK', Label::of(['en' => 'Black']), Swatch::colour('#1A1A1A'), 'black', ['front' => 'blanks/front-bk.jpg']),
            AxisValue::of('PK', Label::of(['en' => 'Pink']), Swatch::colour('#F4A7C0'), 'azalea')),
    ],
    [Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 50, 60, 300, 400))],
    [Exclusion::of(['colour' => ['PK'], 'size' => ['2X']])],
);

$gnome = Design::of('NAQ001', Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
    [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')],
    [Artwork::of('front', 'art/naq001-front.png'), Artwork::of('front', 'art/naq001-front-en.png', language: 'E')]);

foreach (new VariantPlanner()->plan($shirt, $gnome) as $variant) {
    echo $variant->sku, ' ', $variant->price->toDecimal(), ' ', $variant->name('en'), "\n";
}
// NAQ001FOMBK 25.00 Le gnome – T-shirt unisexe (Regular, M, Black)
// ...
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`, et `phil/money`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
