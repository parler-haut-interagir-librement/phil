<!-- phil-dev source-sha256=ff470127d9a2136ac0d748d1fb28e46172595ecacd4b7d6ffd09de43fb993924 -->
# Options

Un gabarit ou un motif déclare des options du noyau du commerce : un texte que tape l'acheteur, un choix parmi des
images, une encre imprimée à part, une valeur tirée après le paiement, comme les décrit le document des options de
`phil/commerce-bundle`. Le module les donne au noyau par `SellableOptions` : `OrderModifier::add()` vérifie donc la
configuration d'une ligne, et le noyau facture ses suppléments. Un montant que choisit l'acheteur est la seule option
qu'un gabarit ne déclare jamais : il appartient à la ligne de commande, et une option enregistrée de ce genre est
refusée à la relecture.

## Les déclarer

```php
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;

$template->declareOptions(
    TemplateOption::of(OptionDefinition::text('name', 'Your name', TextConstraints::of(20, font: 'Display Bold',
        zone: PrintZone::of('nameplate', 40, 300, 200, 40)), supplement: $cad(500))),
    TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark',
        supplement: $cad(300))]), when: ['colour' => ['BK', 'NV']]),
);

$design->declareOptions(
    TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-gnome'), when: ['version' => ['L']]),
);
```

Une `TemplateOption` est une `OptionDefinition` du noyau accompagnée d'une **condition** : des codes de valeurs par
axe, qui disent sur quels produits elle est offerte — l'encre phosphorescente sur les couleurs foncées, le tirage du
butin sur la version boîte à butin. Sans condition, chaque produit du gabarit ou du motif l'a. Chaque code est déclaré
une seule fois par gabarit ou par motif ; `declareOptions()` les remplace toutes.

## Ce que reçoit le noyau

Pour un produit d'un groupe, `TemplateOptions::optionsOf()` donne les options du gabarit, puis celles du motif ; une
option du motif **remplace** l'option du gabarit de même code, offerte ou non. Parmi celles-ci, les options dont les
valeurs du produit remplissent la condition sont gardées, dans cet ordre. Un produit du catalogue hors de tout groupe
n'a aucune option.

Les suppléments comptent dans la devise des prix du gabarit. Une commande dans une autre devise est refusée avec
`TemplateRefused::currency()`, plutôt que facturée dans la mauvaise devise.

`TemplateOptions` répond pour le type de `sellable_type`, `product` par défaut, le type sous lequel vend le catalogue ;
le noyau interroge le premier `SellableOptions` qui prend en charge un type : aucun autre service ne devrait donc
déclarer les options de ce type.

## Stockage

Les options sont gardées sous forme de tableaux de scalaires dans la colonne `options` de leur gabarit ou de leur
motif, et relues par les constructeurs du noyau lui-même : un tableau qu'aucun code du module n'a écrit est refusé
avec `TemplateRefused::unreadable()`.
