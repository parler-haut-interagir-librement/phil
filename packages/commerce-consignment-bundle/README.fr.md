<!-- phil-dev source-sha256=4801e9bcad83b40b159921633bb7ea23dc5947a6576e6a997367dca5c06d109f -->
# phil/commerce-consignment-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/commerce-consignment-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/commerce-consignment-bundle)

**Du stock qui vous appartient et du stock laissé en consignation, côte à côte sur un même SKU : à qui appartient
chaque unité, laquelle sort en premier, la marge ou la commission de chaque vente, et ce que chaque consignataire est
en droit de recevoir, a reçu et s'est fait rendre.**

phil/commerce-consignment-bundle est un bundle Symfony, un module de `phil/commerce-bundle` construit sur le stock de
`phil/commerce-logistics-bundle`. Une boutique qui reçoit des marchandises de distributeurs, d'auteurs et
d'artisans en achète une partie pour la revente et vend le reste en consignation, et un même fournisseur peut faire les
deux sur le même produit : cinq exemplaires d'un livre achetés, cinq autres laissés à vendre contre une part. Le module
de logistique compte les unités en main ; ce bundle garde, dans un grand livre à lui, **à qui elles appartiennent**.

Chaque ligne de réception ouvre une **couche** : des unités d'un SKU, à un emplacement, appartenant à la boutique à un
coût unitaire, ou à un consignataire selon des modalités convenues — une part fixe de chaque unité vendue, ou la
commission de la boutique sous forme de taux. Les ventes, les transferts, les retours et les inventaires prennent des
unités dans les couches et les y remettent, et chaque mouvement est une écriture du grand livre. Quand les deux
sortes de stock sont en main, un **ordre de sortie** configurable dit lesquelles sortent en premier : celles de la
boutique, les consignées ou la réception la plus ancienne. Chaque vente enregistre ce qu'elle a rapporté et sa
**marge** — le prix moins le coût pour les unités de la boutique, la commission — le prix moins la part du
consignataire — pour les unités consignées, ainsi que ce que la boutique doit à chaque consignataire.

Chaque consignataire a une **entente** — combien de temps ses unités restent, ce qui arrive aux invendues, à quelle
fréquence il reçoit un relevé —, versionnée pour qu'un changement ne réécrive jamais le passé. Des **relevés**
numérotés, lus dans le grand livre et figés une fois émis, disent à chaque consignataire ce qui s'est vendu, ce qui est
revenu et ce qui reste en boutique ; la boutique écrit les **versements** qu'elle a faits, partiels ou complets, contre
le solde dû — le bundle ne déplace aucun argent. Les unités invendues sont listées à l'échéance, puis **rendues** à leur
consignataire ou **achetées** par la boutique au coût convenu.

## État

| Partie | État |
|---|---|
| Stock réparti par propriétaire en couches par SKU, emplacement et propriétaire ; grand livre de chaque mouvement | Implémenté et testé |
| Réceptions dont les lignes sont achetées, à un coût unitaire, ou consignées, avec une part en `Money` ou une commission en parties par million | Implémenté et testé |
| Ordre de sortie entre unités à soi et consignées : les siennes d'abord, les consignées d'abord, les plus anciennes d'abord ; par SKU | Implémenté et testé |
| Ventes, retours vers les couches d'origine, transferts entre emplacements, inventaires de tous les propriétaires ou d'un seul | Implémenté et testé |
| Marge des unités de la boutique et commission des unités consignées, par le port `MarginCalculator` | Implémenté et testé |
| Commandes expédiées de `phil/commerce-bundle` écrites au grand livre, une seule fois, quand le module de logistique annonce chaque expédition ; `RecordShipment` pour un worker | Implémenté et testé |
| Retours de clients, écarts d'inventaire et unités rendues déplacés par les mouvements propres au module de logistique ; réceptions vérifiées contre le propriétaire et le coût unitaire de chaque ligne de bon de commande | Implémenté et testé |
| Tables Doctrine sur SQLite, MySQL 8.4 et PostgreSQL 17, clés comparées octet par octet, instants en UTC ; un dépôt en mémoire | Implémenté et testé |
| Ententes avec chaque consignataire — durée, unités invendues, fréquence des relevés —, versionnées sans réécrire le passé | Implémenté et testé |
| Relevés par consignataire et par période, lus dans le grand livre, numérotés et figés ; CSV et gabarit Twig remplaçable | Implémenté et testé |
| Versements écrits, partiels ou complets, refusés deux fois par une clé d'idempotence, annulés par une contrepassation ; le solde et son état | Implémenté et testé |
| Unités invendues à l'échéance, rendues hors du grand livre et de la tablette, ou achetées par la boutique au coût convenu | Implémenté et testé |
| Écrans | Aucun : le bundle offre des services ; l'application dessine ses propres écrans |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, les bundles à enregistrer et les tables. |
| [Configuration](doc/fr/configuration.md) | L'ordre de sortie, pour tous les SKU et par SKU. |
| [Le grand livre de propriété](doc/fr/ledger.md) | Couches, écritures, ordre de sortie, ventes, retours, transferts et inventaires. |
| [Recevoir des marchandises](doc/fr/receiving.md) | Lignes de réception achetées ou consignées, bons de commande, stock déjà en main. |
| [Marges et commissions](doc/fr/margins.md) | Ce qu'une vente a rapporté, coûté ou doit, et comment un taux de commission scinde un prix. |
| [Ententes](doc/fr/agreements.md) | La durée, le sort des unités invendues et la fréquence des relevés de chaque consignataire, version par version. |
| [Relevés](doc/fr/statements.md) | Les périodes, le contenu d'un relevé, les numéros, le CSV et le gabarit. |
| [Versements](doc/fr/payouts.md) | Ce qui a été payé, le même versement deux fois, les contrepassations et le solde. |
| [Unités invendues](doc/fr/unsold-units.md) | Les unités à l'échéance, les bordereaux de retour et les rachats. |
| [Avec le module de logistique](doc/fr/logistics.md) | Comment le stock bouge, les réceptions contre des bons de commande, et comment les commandes expédiées arrivent au grand livre. |
| [Portée](doc/fr/scope.md) | Ce que le bundle fait, et ce qu'il laisse aux versions futures ou à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle::class => ['all' => true],
```

```php
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

// Five copies bought at 12.00, five left by their author for 15.00 each copy sold.
$stock->receive(Receipt::of('DN-1042', 'store', [
    ReceiptLine::purchased('BOOK-123', 5, Money::of(1200, $cad)),
    ReceiptLine::consigned('BOOK-123', 5, 'author-17', ConsignmentTerms::share(Money::of(1500, $cad))),
]), $clock->now());

$sale = $stock->sell('2026-0001', 'BOOK-123', 'store', 6, Money::of(2000, $cad), $clock->now());
$sale->margin();            // revenue, cost or share, margin
$sale->owedToConsignors();  // ['author-17' => 15.00 × the consigned copies sold]
$entityManager->flush();
```

À la fin du mois, le relevé de l'auteur, son versement et les exemplaires qui ne se sont pas vendus :

```php
$author = Owner::consignor('author-17');
$statement = $statements->issue($author, $cad, $statements->lastPeriod($author, $clock->now()), $clock->now());
$payouts->record($transferId, $author, $statement->share(), $clock->now(), 'transfer', null, $clock->now());
$unsold->giveBack('RS-1', $author, 'store', ['BOOK-123' => 2], $clock->now());
$entityManager->flush();
```

## Prérequis

PHP 8.5 ou une version plus récente et Symfony 8.2 ou une version plus récente, avec `phil/commerce-bundle` 0.4 et
`phil/commerce-logistics-bundle` 0.1.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
