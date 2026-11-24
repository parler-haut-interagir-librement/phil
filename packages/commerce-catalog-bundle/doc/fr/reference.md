<!-- phil-dev source-sha256=0be62001179e7eccfd909ef9f5719794f09d752c4d7fa7ba28532c13686e898d -->
# Référence

Chaque type public du module.

| Type | Nature | Rôle |
|---|---|---|
| `PhilCommerceCatalogBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_catalog`, avec `translations.locales` et `product_feeds` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Product` | `final class`, entité Doctrine, `Translatable` | Un produit : *SKU*, nature, nom, prix de base, catégorie de taxe, attributs, offre ; une famille et ses axes, une variante et ses valeurs, un produit composé et ses parties ; les numéros de série ; ses règles de prix ; son prix de détail suggéré |
| `Domain\Model\ProductTranslation` | `final class`, entité Doctrine, `Translation` | Le nom d'un produit dans une locale |
| `Domain\ValueObject\ProductComponent` | `final readonly class` | Une partie d'un produit composé : un *SKU* et ses unités |
| `Domain\Product\ProductKind` | énumération adossée à une chaîne | Physique, numérique, service |
| `Domain\Model\PriceBook` | `final class`, entité Doctrine | Les prix unitaires par groupe, canal, période et quantité ; les grilles après lesquelles elle est essayée |
| `Domain\Model\CommunityCopyFunding` | `final class`, entité Doctrine | Ce qu'une commande a donné pour les copies communautaires d'un produit |
| `Domain\Model\CommunityCopyClaim` | `final class`, entité Doctrine | Une copie communautaire réclamée par une personne |
| `Domain\ValueObject\PriceTier` | `final readonly class` | Le prix unitaire d'un *SKU* à partir d'une quantité |
| `Domain\Exception\CatalogRefused` | `final class`, `DomainError` | Un prix dans une autre devise, un *SKU* pris, des valeurs de variante prises, une partie inconnue ou imbriquée, une catégorie inconnue, une copie communautaire non offerte, épuisée ou déjà réclamée |
| `Domain\Repository\ProductRepository` | interface | Trouve et stocke les produits |
| `Application\Contract\ProductFamilies` | interface | Liste les variantes d'une famille |
| `Domain\Repository\PriceBookRepository` | interface | Trouve et stocke les grilles de prix ; les grilles actives par code |
| `Application\Contract\CommunityCopyLedger` | interface | Tient le financement et les réclamations des copies communautaires, et verrouille un produit pour une réclamation |
| `Application\ProductSellables` | `final readonly class`, le `SellableResolver` du noyau | Les produits vendus sous le type `product`, décrits dans la locale de l'acheteur ; les familles jamais |
| `Application\ProductVariants` | `final readonly class` | Les variantes d'une famille : créées, trouvées par leurs valeurs, les choix qu'elles offrent, leurs attributs avec ceux de la famille, ce que la famille transmet, leur fourchette de prix |
| `Application\PriceRuleSellables` | `final readonly class`, le `SellableResolver` et le `SellableOptions` du noyau | Un prix choisi par l'acheteur, sous `pay_what_you_want`, comme option de montant `price`, et une copie communautaire, sous `community_copy` |
| `Application\CommunityCopies` | `final readonly class`, un écouteur de `OrderPurchased` du noyau | Les copies communautaires financées par les commandes payées, comptées et réclamées |
| `Application\CompositeProducts` | `final readonly class` | Les produits composés : leurs parties vérifiées, et lues dans une ligne de commande |
| `Application\CompositeCosts` | `final readonly class`, `ProductCosts` | Le coût d'un produit, celui d'un produit composé étant la somme de celui de ses parties |
| `Application\ProductTexts` | `final readonly class` | Les noms des produits dans une locale, à partir des traductions stockées ; écrit une traduction |
| `Application\ProductTextSet` | `final readonly class` | Les noms lus pour une liste de produits |
| `Application\Contract\ProductClassification` | interface | Les produits classés dans des catégories et des étiquettes |
| `Application\ProductAvailability` | `final readonly class`, la `SellableAvailability` du noyau | Les sélections des canaux et les offres des produits, celle d'une variante rendue plus stricte par celle de sa famille ; `of()` |
| `Application\Contract\ProductListing` | interface | Les produits qu'un canal montre, page par page |
| `Application\Contract\ProductCosts` | interface | Ce que coûte à la boutique une unité d'un produit |
| `Application\Contract\UnitCosts` | interface | Ce que coûte une unité d'un *SKU*, d'après les valeurs que donne le stock |
| `Application\ListedProduct` | `final readonly class` | Un produit comme le montre une liste, lu sans son entité ; `isFamily()`, `value()`, `named()` |
| `Application\ProductPage` | `final readonly class` | Une page de produits et le curseur de la suivante |
| `Application\PriceBookPrices` | `final class`, le `UnitPriceResolver` du noyau, étiqueté `kernel.reset` | Les prix des grilles, les grilles actives lues et classées une seule fois jusqu'à `reset()` |
| `Application\PriceBookOrder` | `final class` | L'ordre dans lequel les grilles de prix sont essayées, à partir de leurs listes `after` |
| `Application\ProductSchema` | `final class` | Un produit comme un `Product` de schema.org avec son `Offer`, une variante avec sa famille, les attributs donnés |
| `Infrastructure\Taxonomy\TaxonomyProductClassification` | `final readonly class` | `ProductClassification` sur `phil/taxonomy-bundle`, un produit étant la ressource `phil_commerce_product` |
| `Infrastructure\Doctrine\DoctrineProductRepository` | `final readonly class` | Les produits et les variantes d'une famille par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrinePriceBookRepository` | `final readonly class` | Les grilles de prix par le gestionnaire d'entités |
| `Infrastructure\Doctrine\DoctrineCommunityCopyLedger` | `final readonly class` | Les copies communautaires par le gestionnaire d'entités, additionnées et comptées en SQL |
| `Infrastructure\Doctrine\PriceBookOrderMigration` | `final class` | Fait passer une table de grilles classées par `priority` aux listes `after`, depuis une migration de l'application |
| `Infrastructure\Doctrine\DbalProductListing` | `final readonly class` | `ProductListing` sur DBAL, par un curseur sur le *SKU* |
| `Infrastructure\Logistics\LogisticsUnitCosts` | `final readonly class`, `UnitCosts` | Les coûts unitaires du calcul des coûts de `phil/commerce-logistics-bundle` |
| `Infrastructure\ProductFeeds\FeedDefinition` | `final readonly class` | Un flux de la configuration ; `address()` remplit un gabarit |
| `Infrastructure\ProductFeeds\CatalogProductSource` | `final readonly class`, le `ProductSource` de `phil/product-feeds` | Les produits qu'un canal liste avec leur prix, comme les articles d'un flux |
| `Infrastructure\ProductFeeds\ProductFeeds` | `final readonly class` | Publie les flux de la configuration par nom ; `names()`, `definition()`, `publish()` |
| `Infrastructure\ProductFeeds\PublishProductFeed` | `final readonly class` | Le message qui demande un flux, par son nom |
| `Infrastructure\ProductFeeds\PublishProductFeedHandler` | `final readonly class` | Publie le flux qu'un message nomme |
| `Infrastructure\ProductFeeds\PublishProductFeedCommand` | `final readonly class`, une commande invocable | `phil:commerce-catalog:publish-feeds [feed…]` |
| `Infrastructure\Http\ProductFeedController` | `final readonly class`, un contrôleur | Sert le fichier d'un flux à `/product-feeds/{feed}`, avec ses en-têtes de cache |

Voyez [scope.md](scope.md) pour ce qui n'est pas commencé.
