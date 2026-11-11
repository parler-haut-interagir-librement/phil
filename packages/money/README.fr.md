<!-- phil-dev source-sha256=cd03fa00ec9c8ce20bb65bc18f7b50ac6574e2366fbaa372c43ea0a1525821ac -->
# phil/money

**Des montants d'argent sous forme d'entiers exacts dans une devise connue, sans aucun nombre à virgule flottante.**

phil/money est un objet-valeur pour l'argent : un montant dans la plus petite unité de sa devise, et une devise que
connaissent les données Unicode CLDR. Les montants s'additionnent, se soustraient, se multiplient par des nombres
entiers et se comparent seulement à l'intérieur d'une même devise. Les décimaux se lisent et s'écrivent sous forme de
chaînes, à la précision propre à la devise. Un montant se répartit selon des proportions ou en parts égales sans perdre
ni créer d'unité, et s'affiche sous forme de `MonetaryAmount` ou de prix d'`Offer` de schema.org. La bibliothèque ne
convertit pas les devises, ne garde aucun taux de change, n'arrondit pas les fractions d'unité, ne met pas en forme les
montants pour l'affichage et ne sait pas quelles devises une application accepte.

Elle s'adresse à tout code PHP qui manipule des prix, des totaux ou des remboursements et qui ne peut pas se permettre
de perdre un cent à cause de l'arrondi des nombres à virgule flottante. Les autres paquets phil/* qui traitent des
montants, comme `phil/tax` et `phil/commerce-bundle`, parlent tous son `Money`.

C'est une simple bibliothèque PHP sans cadriciel, construite sur `symfony/intl` pour les données des devises. Dans une
application Symfony, tournez-vous vers `phil/money-bundle`, qui ajoute les devises acceptées, un type de formulaire, la
validation, Twig et Doctrine.

Deux types immuables portent la conception, `Money` et `Currency`. Chaque opération retourne une nouvelle valeur, et une
opération entre deux devises lance une exception au lieu de deviner.

## État

| Partie | État |
|---|---|
| `Currency` : codes ISO 4217 connus des données CLDR, précision, nom et symbole localisés | Implémenté et testé |
| `Money` : arithmétique entière, comparaison, lecture et écriture des décimaux | Implémenté et testé |
| Répartition selon des proportions et en parts égales | Implémenté et testé |
| `SchemaOrg` : `MonetaryAmount` et prix d'`Offer` de schema.org | Implémenté et testé |
| Exceptions sous une seule interface `MoneyException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Devises](doc/fr/currencies.md) | `Currency` : les codes acceptés, la précision, le nom et le symbole. |
| [Montants](doc/fr/amounts.md) | `Money` : la création, les décimaux, l'arithmétique, la comparaison et l'intervalle des entiers. |
| [Répartition](doc/fr/allocation.md) | `allocate()` et `split()` : comment les unités restantes sont distribuées. |
| [Exceptions](doc/fr/exceptions.md) | `MoneyException` et les trois exceptions, avec leurs messages. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui compte de l'argent. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/money
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

$price = Money::ofDecimal('19.99', $cad);
$total = $price->multiply(3)->add(Money::of(500, $cad));
$total->toDecimal(); // "64.97"

[$first, $second, $third] = Money::ofDecimal('100', $cad)->split(3); // 33.34, 33.33, 33.33
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, et `symfony/intl` 8.2 pour les données des devises.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
