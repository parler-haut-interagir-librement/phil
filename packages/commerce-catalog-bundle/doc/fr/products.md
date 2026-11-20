<!-- phil-dev source-sha256=c2c5b8deda53c4033fe6d43d12e8565f99225fa0684e151fbe2453a53ff88202 -->
# Produits

Un produit est nommé par son *SKU* (unité de gestion de stock, ou *UGS*) et vendu au noyau sous le type de référence
`product` : `product:DICE-D20` est une unité à son prix de base — ou à celui d'une grille de prix — dans sa catégorie
de taxe.

```php
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\Money\Currency;
use Phil\Money\Money;

$dice = Product::create('DICE-D20', ProductKind::Physical, 'Dé à 20 faces', Money::of(499, Currency::of('CAD')), 'goods');
$dice->describe('colour', 'rouge');
$products->add($dice); // ProductRepository; the application commits its unit of work
```

| Membre | Règle |
|---|---|
| `sku` | Jusqu'à 64 lettres, chiffres, `.`, `_` ou `-`, commençant par une lettre ou un chiffre |
| `kind` | `Physical` (expédié ; le module de logistique compte son stock), `Digital`, `Service` |
| `name()`, `rename()` | De 1 à 255 caractères, pas vide : la description de la ligne de commande |
| `price`, `reprice()` | Zéro ou plus, dans la devise du produit : le prix de base |
| `taxCategory()`, `classify()` | De 1 à 64 caractères : la catégorie que lisent les taux de taxe du noyau |
| `attributes()`, `attribute()`, `describe()` | Des noms d'au plus 64 lettres minuscules, chiffres ou `_` ; des valeurs de 1 à 255 caractères ; `null` en retire une |
| `channels()`, `sellThrough(list)`, `isSoldThrough(channel)` | Les canaux qui le vendent ; tous les canaux quand la liste est vide ; une commande sans canal n'obtient que ce que vendent tous les canaux |
| `offer()`, `changeOffer(Offer)`, `offerThrough(channel)` | Ce qu'offrent d'un produit les canaux qui le vendent : listé, commandable et avec prix par défaut ; caché là où il est retiré ou hors de la sélection ; une variante commence avec l'offre de sa famille |
| `withdraw()`, `restore()` | Un produit retiré n'est pas vendu : le noyau ne trouve rien pour sa référence |
| `defineAxes()`, `axes()`, `isFamily()` | Une famille, choisie par ses variantes et jamais vendue elle-même — voyez [Variantes et produits composés](variants-and-composites.md) |
| `family()`, `options()` | La famille d'une variante, et sa valeur sur chaque axe |
| `composeOf()`, `components()`, `isComposite()` | Un produit composé et ses parties |
| `trackSerialNumbers(bool)`, `tracksSerialNumbers()` | Chaque unité vendue porte un numéro de série ; produits physiques seulement ; une variante prend celui de sa famille |
| `payWhatYouWant()`, `fixPrice()`, `offerCommunityCopies()` | Les règles de prix — voyez [Règles de prix](price-rules.md) |

**Les numéros de série** sont tenus avec le stock du module de logistique, qui enregistre le numéro de chaque unité ;
le catalogue dit quels produits en portent un, pour que l'application le demande à une vente ou à une réception.

## Les offres

Un produit est offert par l'`Offer` du noyau : une `Visibility`, puis si le panier le prend et si son prix est montré.

| Offre | Sa page | Les listes et la recherche | Son prix | Le panier |
|---|---|---|---|---|
| `Offer::full()`, la valeur par défaut | Montrée | Listé | Montré | Le prend |
| `Offer::of(Visibility::Unlisted)` | Montrée par son lien direct : une édition de congrès, une prévente | Absent | Montré | Le prend |
| `Offer::of(Visibility::Listed, orderable: false)` | Montrée : épuisé pour de bon | Listé | Montré | Le refuse |
| `Offer::of(Visibility::Listed, orderable: false, priceShown: false)` | Montrée : une annonce | Listé | Caché | Le refuse |
| `Offer::hidden()` | Absente | Absent | Caché | Le refuse |

```php
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;

$dice->changeOffer(Offer::of(Visibility::Listed, orderable: false)); // its page stays, without a button to buy it
$preview->changeOffer(Offer::of(Visibility::Unlisted));             // sold to whoever has the link
```

Dans un canal, l'offre est celle du produit, sauf si le produit est retiré ou hors de la sélection du canal — il y est
alors caché. Une variante est offerte au plus strict de sa propre offre et de celle de sa famille : une famille cachée
cache ses variantes, une famille laissée à son lien direct les y laisse, une famille non commandable les rend non
commandables. Une nouvelle variante commence avec l'offre de sa famille.

`ProductAvailability` répond à `SellableAvailability` du noyau avec cette offre, pour le produit, son prix choisi
— `pay_what_you_want:<sku>` — et ses copies communautaires. Les pages la lisent par
`SellableOffers::of($reference, $channel)` du noyau — ou par `ProductAvailability::of($product, $channel)` pour un
produit qu'elles tiennent déjà — : elles listent un produit
`isListed()`, montrent la page de celui qui est `isReachable()`, son prix quand `priceShown`, le bouton qui l'achète
quand `orderable`, et répondent qu'un produit caché est introuvable. Le panier refuse ce qui n'est pas `orderable` par
`NotSoldThere`, au travers d'`OrderModifier`.

## Les listes de produits

`ProductListing` liste les produits qu'un canal liste — jamais un produit laissé à son lien direct —, page par page,
pour une page de catalogue ou un flux de n'importe quelle longueur. `DbalProductListing` les lit sur DBAL, sans entités,
par *SKU* : le curseur d'une page nomme l'endroit où la suivante commence.

```php
$page = $listing->page('web', after: null, limit: 50, locale: 'fr');

foreach ($page->products as $product) { // ListedProduct
    // $product->sku, ->name, ->price, ->offer, ->value('colour')
}

$next = $listing->page('web', after: $page->next, limit: 50, locale: 'fr'); // none once $page->next is null
```

| Argument | Règle |
|---|---|
| `channel` | Le canal dont la sélection est listée ; `null` liste ce que vendent tous les canaux |
| `after` | Le curseur de la page précédente, `ProductPage::$next` |
| `limit` | De 1 à 500 produits |
| `locale` | Les noms dans cette locale, le long de la chaîne de `translations.locales` ; le nom propre de chaque produit sinon |
| `families` | Si les familles sont listées à côté de leurs variantes ; `true` par défaut |
| `direction` | `\SortDirection::Ascending` par *SKU* par défaut, ou `Descending` |

Un produit est listé quand il est actif, dans la sélection du canal, et que ni lui ni sa famille n'est caché ; il vient
avec le plus strict des deux niveaux. Les produits actifs qui ne sont pas cachés sont lus par lots de la taille de la
page, et la sélection, gardée en JSON, est vérifiée sur chaque rangée ; une page lit dix lots au plus, si bien qu'un
canal qui vend peu de produits parmi beaucoup peut obtenir une page courte, dont le curseur se lit quand même. Les noms
d'une page arrivent en une seule requête.

Un produit est retiré plutôt que supprimé, pour que les commandes et les reçus qui le nomment gardent leur sens.

## Stockage

Avec DoctrineBundle, un produit est une rangée de `phil_commerce_catalog_product`.

| Colonne | Type | Contient |
|---|---|---|
| `sku` | `VARCHAR(64)`, clé primaire | Le *SKU* |
| `kind` | `VARCHAR(16)` | `physical`, `digital` ou `service` |
| `name` | `VARCHAR(255)` | Le nom |
| `price`, `currency` | `BIGINT`, `CHAR(3)` | Le prix de base, en unités mineures |
| `tax_category` | `VARCHAR(64)` | La catégorie de taxe |
| `attributes` | JSON | Attribut → valeur |
| `channels` | JSON | Les canaux qui le vendent |
| `visibility` | `VARCHAR(16)`, `listed` par défaut | `listed`, `unlisted` ou `hidden` |
| `orderable`, `price_shown` | `BOOLEAN`, `true` par défaut | Si le panier le prend, si son prix est montré |
| `active` | `BOOLEAN`, indexé | `false` une fois retiré |
| `family` | `VARCHAR(64)`, nullable, indexé | Le *SKU* de la famille d'une variante |
| `axes` | JSON | Les axes d'une famille, dans l'ordre |
| `options` | JSON | Axe → valeur d'une variante |
| `components` | JSON | Les parties d'un produit composé : *SKU* et unités |
| `serial_numbers` | `BOOLEAN` | Si chaque unité porte un numéro de série |
| `pay_what_you_want` | `BOOLEAN` | Si l'acheteur choisit le prix |
| `suggested_price`, `maximum_price` | `BIGINT`, nullable | Le prix montré d'abord et le plus haut, en unités mineures ; sans maximum, le `pay_what_you_want.default_maximum` de la configuration borne le prix |
| `community_copy_price` | `BIGINT`, nullable | Le prix d'une copie communautaire, en unités mineures |

Le nom d'un produit dans les autres locales est une rangée de `phil_commerce_catalog_product_translation` par locale :
voyez [Noms et classement](names-and-classification.md).

## schema.org

```php
$data = ProductSchema::of($product, $productUrl, $priceForTheVisitor, $nameInThePagesLocale, $variants->attributesOf($product));
// all optional but the product
```

Un `Product` — nom, *SKU*, page, chaque attribut donné (les siens par défaut) et chaque option d'une variante comme une
`PropertyValue`, la famille d'une variante comme `inProductGroupWithID` — avec son `Offer` : le prix donné (d'une grille de prix ou après une
promotion), sinon le prix de base, comme `price` et `priceCurrency` de `phil/money`, et `availability` à `InStock`, ou
à `Discontinued` une fois le produit retiré. L'application l'imprime en JSON-LD avec `JSON_HEX_TAG`.
