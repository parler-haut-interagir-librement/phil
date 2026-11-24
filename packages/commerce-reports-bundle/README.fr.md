<!-- phil-dev source-sha256=8666f2824e6087e68186f9ac26328004be4add8184606f4588d6e4ea14bae505 -->
# PHIL Commerce Reports Bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/commerce-reports-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/commerce-reports-bundle)

**Know what sells and what sits on the shelf: gross and net sales by product, category, channel, location and
cashier, sell-through and dead stock reports.**

phil/commerce-reports-bundle est un bundle Symfony. Il ajoute trois rapports à
[phil/reporting-bundle](https://github.com/parler-haut-interagir-librement/reporting-bundle) — exportés en CSV, en TSV
ou en JSON, planifiés et livrés par ce bundle — et lit les modules de commerce par ses propres ports : les lignes
vendues, les lignes retournées, les chiffres de stock d'une UGS et les UGS en stock.

## État

| Partie | État |
|---|---|
| Ventes brutes et nettes par produit, catégorie, canal, lieu et caissier, une ligne par devise (`commerce_sales`) | Implémenté |
| Écoulement en parties par million, par UGS et par catégorie (`commerce_sell_through`) | Implémenté |
| Stock dormant après un nombre de jours sans vente (`commerce_dead_stock`) | Implémenté |
| Les ports `SoldLines`, `ReturnedLines`, `StockFigures`, `StockedSkus`, avec des adaptateurs en mémoire | Implémenté |
| Les lignes vendues au point de vente (`PosSoldLines`) et les chiffres de stock du module de logistique (`LogisticsStockFigures`) | Implémenté |
| Marge, coût des marchandises vendues, rotation, jours de stock, rendement de la marge brute sur les stocks et démarque | Prévu : ils exigent le coût du stock, que le module de logistique n'enregistre pas encore |
| Ventes et stock répartis par propriétaire du stock, pour la consignation | Prévu |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement des ports. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Les rapports](doc/fr/reports.md) | Chaque rapport, ses paramètres, ses colonnes et ses formules. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les adaptateurs fournis avec le bundle, et ce que chaque module ne liste pas encore. |
| [Tests](doc/fr/testing.md) | Les tests du bundle. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
Phil\CommerceReportsBundle\PhilCommerceReportsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_commerce_reports.yaml
phil_commerce_reports:
    dead_stock_days: 90
```

```sh
bin/console phil:reporting:export commerce_sales --period=last_month --output=var/sales.csv
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
