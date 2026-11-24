<!-- phil-dev source-sha256=722144afe72c13e6db32aab43401ab121fde710ca877ee979a72299ecbe270ae -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application étend ou remplace. Les ports du noyau
et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette et ordre |
|---|---|---|
| `SellableResolver` | `ProductSellables`, pour le type de référence `product` | `phil_commerce.sellable_resolver` |
| `SellableResolver` | `PriceRuleSellables`, pour les types de référence `pay_what_you_want` et `community_copy` | `phil_commerce.sellable_resolver` |
| `SellableOptions` | `PriceRuleSellables`, l'option de montant `price` du type de référence `pay_what_you_want` | `phil_commerce.sellable_options` |
| `UnitPriceResolver` | `PriceBookPrices` | `phil_commerce.unit_price_resolver` |
| `SellableAvailability` | `ProductAvailability`, l'`Offer` du produit dans le canal | `phil_commerce.sellable_availability` |

Un résolveur ou un processeur propre à l'application, pour le même port, est étiqueté par l'autoconfiguration du
noyau. Il nomme ces services dans le `before` ou le `after` de son étiquette pour prendre sa place dans l'ordre : un
prix à lui avant ou après les grilles de prix. Voyez [Portée](scope.md).

## Les dépôts

Chaque port de dépôt est un alias public de l'adaptateur Doctrine du module ; une application le fait pointer vers un
de ses propres services sous le même contrat.

| Port | Adaptateur |
|---|---|
| `ProductRepository` | `DoctrineProductRepository` |
| `ProductFamilies` | `DoctrineProductRepository` |
| `PriceBookRepository` | `DoctrinePriceBookRepository` |
| `Application\Contract\CommunityCopyLedger` | `DoctrineCommunityCopyLedger` |

## Les ports qu'il offre

| Port | Adaptateur |
|---|---|
| `Application\Contract\ProductClassification` | `TaxonomyProductClassification`, sur `phil/taxonomy-bundle`, enregistré quand sa taxonomie l'est ; sinon, une application fait pointer le port vers un de ses propres services |
| `Application\Contract\ProductListing` | `DbalProductListing`, un alias public ; une application qui stocke ses produits autrement le fait pointer vers un de ses propres services — les flux le lisent aussi |
| `ProductSource` de `phil/product-feeds` | `CatalogProductSource`, un par flux, construit par `ProductFeeds` |
| `Application\Contract\ProductCosts` | `CompositeCosts`, un alias public : le coût d'une unité d'un produit, celui d'un produit composé étant la somme de celui de ses parties — voyez [Variantes et produits composés](variants-and-composites.md#le-coût-dun-produit-composé) |
| `Application\Contract\UnitCosts` | `LogisticsUnitCosts`, sur le calcul des coûts de `phil/commerce-logistics-bundle`, enregistré quand son bundle l'est ; sinon, une application fait pointer le port vers un de ses propres services |


## Les services

| Service | Comment une application l'utilise |
|---|---|
| `ProductVariants` | Crée les variantes d'une famille, en trouve une par ses valeurs, liste les choix ; public — voyez [Variantes et produits composés](variants-and-composites.md) |
| `CompositeProducts` | Fait d'un produit un composé d'autres, et lit les parties d'une ligne de commande ; public |
| `ProductTexts` | Lit les noms des produits dans une locale et écrit leurs traductions ; public — voyez [Noms et classement](names-and-classification.md) |
| `ProductFeeds` | Publie un flux de la configuration par nom ; public — voyez [Flux de produits](product-feeds.md) |
| `PublishProductFeedHandler` | Publie le flux que nomme un `PublishProductFeed` ; étiqueté `messenger.message_handler` quand Messenger est installé |
| `PublishProductFeedCommand` | `phil:commerce-catalog:publish-feeds` ; étiquetée `console.command` quand Console est installé |
| `CommunityCopies` | Compte et donne les copies communautaires ; écoute `OrderPurchased` du noyau par l'étiquette `kernel.event_listener` ; public — voyez [Règles de prix](price-rules.md) |

## L'ordre des grilles de prix

La liste `after` d'une grille la place après d'autres grilles : une application ajoute une grille entre deux autres en
les nommant, et `PriceBookOrder` les trie comme Symfony trie les services étiquetés. Un résolveur de prix propre à
l'application se place parmi ceux du noyau par le `before` et le `after` de son étiquette, comme ci-dessus.

## Les traductions

La description de la ligne d'une copie communautaire est la clé `phil_commerce_catalog.community_copy` du domaine
`phil_commerce_catalog`, avec le paramètre `%name%`, et le libellé de l'option d'un prix choisi la clé
`phil_commerce_catalog.chosen_price`, `Your price` ; les deux sont traduites dans `translations/phil_commerce_catalog.en.php`
et `.fr.php`, et une application les remplace dans ses propres traductions.
