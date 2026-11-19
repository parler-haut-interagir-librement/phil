<!-- phil-dev source-sha256=3e1900a7b0965a1525e38275dac7a5866c3b2e177a09b21491a726649aa08c7e -->
# Variantes et produits composés

## Familles et variantes

Une **famille** nomme les axes selon lesquels ses variantes diffèrent — un t-shirt par taille et par couleur —, dans
l'ordre où un acheteur les choisit. On la choisit par ses variantes et elle n'est jamais vendue elle-même : le noyau
ne trouve rien pour sa référence. Une **variante** est un produit à part entière, avec son *SKU*, son prix, ses canaux
et son stock, qui donne à chaque axe de sa famille une valeur.

```php
use Phil\CommerceCatalogBundle\Application\ProductVariants;

$shirt = Product::create('TEE01', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
$shirt->defineAxes(['size', 'colour']);
$products->add($shirt);

$variants->create($shirt, 'TEE01-M-BLACK', ['size' => 'M', 'colour' => 'black']);           // "T-shirt — M / black", 25.00
$variants->create($shirt, 'TEE01-XXL-BLACK', ['size' => 'XXL', 'colour' => 'black'], Money::of(2_800, Currency::of('CAD')));

$variants->find($shirt, ['colour' => 'black', 'size' => 'M']);   // the variant, whatever order the values come in
$variants->choices($shirt);                                      // ['size' => ['M', 'XXL'], 'colour' => ['black']]
```

| Règle | |
|---|---|
| Axes | Jusqu'à 5, chacun d'au plus 64 lettres minuscules, chiffres ou `_`, commençant par une lettre ; une variante ou un produit composé n'en nomme aucun |
| Valeurs | Une par axe, de 1 à 64 caractères, pas vide ; aucune autre option |
| Une variante prend | La nature, la catégorie de taxe et les canaux de sa famille ; son nom et son prix aussi, sauf s'ils sont donnés — le nom est celui de la famille suivi de ses valeurs |
| `ProductVariants::create()` | Refuse un *SKU* déjà pris et une seconde variante de la famille avec les mêmes valeurs, retirées comprises |
| `ProductVariants::choices()` | Les valeurs des variantes encore vendues, axe par axe, chacune une fois, dans l'ordre des *SKU* des variantes |

### Ce qu'une variante prend de sa famille

| Quoi | Comment |
|---|---|
| Attributs | `ProductVariants::attributesOf($variant)` : ceux de la famille, avec ceux de la variante par-dessus ; ce que montrent une page et `ProductSchema` |
| Catégorie de taxe, canaux, numéros de série | Copiés à la création de la variante ; `ProductVariants::cascade($family)` les copie de nouveau à chaque variante et retourne les variantes changées |
| Nom, prix, options | Ceux de la variante elle-même, une fois créée |

`ProductVariants::priceRange($family)` donne le prix de base le plus bas et le plus haut des variantes encore vendues,
ou `null` quand il n'y en a aucune, pour une famille montrée avec une fourchette de prix.

Une variante est stockée comme n'importe quel produit, avec le *SKU* de sa famille dans `family` et ses valeurs dans
`options`. `ProductFamilies::variantsOf()`, que l'adaptateur Doctrine implémente à côté de `ProductRepository`, liste
les variantes d'une famille par *SKU*. Un catalogue généré à partir de gabarits — le motif d'un t-shirt croisé avec ses
tailles et ses couleurs — crée ses variantes par `ProductVariants`.

## Produits composés

Un produit **composé** est fait d'autres produits, chacun avec un nombre d'unités — une boîte de départ faite d'un
livre de règles et de deux jeux de dés. Il est vendu comme un seul produit, sur une seule ligne, à son propre prix et
dans sa propre catégorie de taxe : le tout est taxé ensemble, jamais partie par partie.

```php
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;

$composites->compose($box, [ProductComponent::of('RULEBOOK'), ProductComponent::of('DICE-SET', 2)]);
$composites->partsOf(SellableReference::of('product', 'BOX'));   // the parts one unit of a line holds
```

| Règle | |
|---|---|
| Parties | Jusqu'à 50, chacune un produit du catalogue nommé une seule fois, jamais le produit composé lui-même, de 1 à 1 000 unités |
| Profondeur | Un seul niveau : une partie n'est ni une famille ni un produit composé |
| Une famille | N'est jamais un produit composé |
| Aucune partie | `compose($product, [])` en refait un produit à part |

Les parties sont ce que le stock distribue et ce qu'un retour reprend en entier : `partsOf()` les lit dans la référence
d'une ligne de commande, et n'en trouve aucune pour un produit à part ou un autre article vendable. Une promotion
entre deux produits vendus séparément — deux lignes, chacune avec sa part du rabais — relève plutôt d'un lot de
`phil/commerce-promotions-bundle`.
