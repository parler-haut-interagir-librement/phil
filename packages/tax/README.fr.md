<!-- phil-dev source-sha256=2a002a2f9a62a3205eb2d923aac73e2d68314c5b2a7238d9f47ca7c4079d82fe -->
# phil/tax

**Plusieurs taxes sur une même vente, chacune calculée sur la même base et détaillée au cent près.**

phil/tax calcule une taxation à plusieurs taux sur une base commune : une vente soumise à plusieurs taxes à la fois,
par exemple une taxe fédérale et une taxe provinciale, chacune calculée sur la même base, arrondie sur sa propre ligne
et détaillée sur la facture. Il extrait aussi les taxes que contient un prix taxes incluses. Il ne décide pas quels
taux s'appliquent à une vente, si un vendeur doit percevoir la taxe, quel territoire taxe une vente, ni ce qu'un reçu
doit mentionner. Il ne contient aucun taux en vigueur et n'applique jamais une taxe sur une autre. Tout cela revient à
l'application ou au moteur de taxation qui la sert.

Il s'adresse à tout code PHP qui émet des factures, des reçus ou des notes de crédit et doit afficher chaque taxe sur
sa propre ligne. `phil/commerce-bundle` l'utilise pour taxer chaque ligne de commande ; une application sans boutique
peut l'appeler directement.

C'est une simple bibliothèque PHP dont la seule dépendance est `phil/money`, dont elle prend et retourne les `Money`.
Les taux sont exprimés en parties par million, jamais en nombre à virgule flottante. Chaque taxe est arrondie à la
moitié en s'éloignant de zéro, en arithmétique entière : une note de crédit annule donc exactement une facture.

La conception est sobre : une valeur `TaxRate`, un `MultiRateTaxCalculator` qui applique une liste de taux, et un
`TaxBreakdown` de `TaxLine` que la facture imprime.

## État

| Partie | État |
|---|---|
| `TaxRate` : code, parties par million, pourcentage lu et imprimé, taxe sur une base | Implémenté et testé |
| `TaxLine` : calculée, ou fournie par un moteur externe | Implémenté et testé |
| `TaxBreakdown` : lignes sur une même base, totaux, ligne par code, inversion pour une note de crédit | Implémenté et testé |
| `MultiRateTaxCalculator` : chaque taux sur la même base | Implémenté et testé |
| Exceptions sous une même interface `TaxException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Taux](doc/fr/rates.md) | `TaxRate` : les codes, les parties par million, les pourcentages, l'arrondi et la plus grande base. |
| [Ventilations](doc/fr/breakdowns.md) | `TaxLine`, `TaxBreakdown` et `MultiRateTaxCalculator` : une seule base, l'arrondi ligne par ligne, l'inversion. |
| [Choisir les taux](doc/fr/choosing-rates.md) | Ce que la bibliothèque laisse à l'application, et un résolveur qu'elle peut écrire. |
| [Exceptions](doc/fr/exceptions.md) | `TaxException` et les trois exceptions, avec leurs messages. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests et le seuil de mutation de la bibliothèque, et comment tester du code qui taxe. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/tax
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxRate;

$breakdown = new MultiRateTaxCalculator()->calculate(Money::ofDecimal('19.99', Currency::of('CAD')), [
    TaxRate::ofPercent('GST', '5'),
    TaxRate::ofPercent('QST', '9.975'),
]);

$breakdown->line('QST')?->amount->toDecimal(); // "1.99", on 19.99 and not on 19.99 plus GST
$breakdown->totalWithTax()->toDecimal();       // "22.98"
$credit = $breakdown->reversed();              // every line negated
```

## Prérequis

PHP 8.5 ou plus récent, et `phil/money` 0.1.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
