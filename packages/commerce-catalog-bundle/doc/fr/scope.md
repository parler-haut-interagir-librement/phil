<!-- phil-dev source-sha256=9eea271b0098d53069f3944def8314435885bc0dea376e113400147a40acae3f -->
# Portée

Le catalogue du noyau du commerce : les produits et leurs attributs, les familles et les variantes, les produits
composés, les noms traduits, les catégories et les étiquettes, les grilles de prix et les règles de prix. Les promotions sur ses produits
sont le module `phil/commerce-promotions-bundle`.

| Partie | Contenu | État |
|---|---|---|
| Produits | Les produits physiques, numériques et de service avec leurs attributs, vendus par le `SellableResolver` du noyau sous le type de référence `product` | Implémenté et testé ([products.md](products.md)) |
| Sélections et offres | Les canaux qui vendent chaque produit, et ce qu'ils en offrent — listé, par lien direct seulement ou caché ; commandable ou non ; avec prix ou non —, une variante au plus strict de sa propre offre et de celle de sa famille, comme `SellableAvailability` du noyau ; le panier refuse ce qui n'est pas commandable | Implémenté et testé ([products.md](products.md#les-offres)) |
| Listes | Les produits qu'un canal montre, page par page sur DBAL, par un curseur sur le *SKU*, avec leurs noms dans une locale | Implémenté et testé ([products.md](products.md#les-listes-de-produits)) |
| Flux de produits | Le catalogue publié comme fichiers de flux RSS ou TSV par `phil/product-feeds`, par une commande ou un message qu'envoie un calendrier, et servi par une route avec des en-têtes de cache | Implémenté et testé ([product-feeds.md](product-feeds.md)) ; pousser vers l'API d'une place de marché est une étape ultérieure |
| Grilles de prix | Les prix par groupe de clients, canal — un site, un point de vente —, période et palier de quantité, classées par leurs listes `after`, comme `UnitPriceResolver` du noyau ; une migration depuis les grilles classées par numéro | Implémenté et testé ([price-books.md](price-books.md)) |
| Variantes | Des familles qui nomment leurs axes, et des variantes qui donnent une valeur à chaque axe et prennent les attributs, la catégorie de taxe, les canaux et les numéros de série de leur famille | Implémenté et testé ([variants-and-composites.md](variants-and-composites.md)) |
| Produits composés | Un produit fait d'autres produits, vendu comme une seule ligne et taxé en bloc | Implémenté et testé ([variants-and-composites.md](variants-and-composites.md)) |
| Noms | Les noms des produits traduits, lus le long d'une chaîne de repli | Implémenté et testé ([names-and-classification.md](names-and-classification.md)) |
| Taxonomie | Les catégories et les étiquettes, par `phil/taxonomy-bundle` | Implémenté et testé ([names-and-classification.md](names-and-classification.md)) |
| Règles de prix | Payez ce que vous voulez, du prix de base jusqu'à un maximum — celui du produit ou celui par défaut du site —, avec un prix suggéré, par l'option de montant du noyau ; des copies communautaires financées par les commandes payées et réclamées une par personne | Implémenté et testé ([price-rules.md](price-rules.md)) |
| Numéros de série | Quels produits en portent un ; les numéros eux-mêmes sont tenus avec le stock du module de logistique | Implémenté et testé ([products.md](products.md)) |
| Avis | Les avis sur un produit, publiés après modération | Pas commencé : ils viennent de `phil/reviews-bundle`, dont il ne tient que le squelette ; le catalogue n'en montre aucun tant qu'il n'est pas écrit |
| Produits qu'un acheteur assemble | Un produit construit à partir des choix de l'acheteur | Pas commencé : il est prévu après cette version, avec les autres parties qui suivent le catalogue |
| Une fourchette de prix en mots | Le prix le plus bas et le plus haut d'une famille écrits comme une seule fourchette dans la locale de l'acheteur | Pas commencé : `priceRange()` donne les deux prix ; les écrire comme une seule fourchette attend un filtre de fourchette de `phil/money-bundle` |

## Les ports du noyau qu'il implémente

- `SellableResolver`, pour le type de référence `product` : `ProductSellables` ; pour `pay_what_you_want` et
  `community_copy` : `PriceRuleSellables`.
- `SellableOptions`, pour le type de référence `pay_what_you_want` : `PriceRuleSellables`, qui déclare l'option de
  montant d'un prix choisi.
- `UnitPriceResolver` : `PriceBookPrices`.
- `SellableAvailability` : `ProductAvailability`, qui répond par l'`Offer` du produit dans le canal.

## La bibliothèque qu'il alimente

- `phil/product-feeds`, suggéré : `CatalogProductSource` est son `ProductSource`, et `ProductFeeds` publie les flux que
  déclare la configuration avec son `FeedPublisher`.

## Les événements du noyau qu'il écoute

- `OrderPurchased` : `CommunityCopies`, qui enregistre ce qu'une commande payée a donné pour les copies
  communautaires.
