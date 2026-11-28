<!-- phil-dev source-sha256=99500aee52f2b7d09f963a344d882c1e66aa835179c9195d8dd8ba8db8b47a40 -->
# Lots

## Ce qu'est un lot

Un `ProductBundle` est une UGS (unité de gestion de stock) à lui sur les produits qu'il contient :

| Partie | Règle |
|---|---|
| `sku` | Jusqu'à 64 lettres, chiffres, `_`, `.`, `/` ou `-`, commençant par une lettre ou un chiffre. |
| `kind` | `BundleKind::Assembled` ou `BundleKind::Collection` : la façon dont il est taxé. |
| `currency` | La devise de son prix ; un prix fixe doit y être. |
| `components` | De 1 à 50 `BundleComponent`, chaque SKU une seule fois, jamais le SKU du lot lui-même. |
| `price` | Une `PricePolicy` : un prix fixe ou un rabais sur les composants. |

Un `BundleComponent` est un SKU et les unités qu'un lot en contient, de 1 à 1 000. Deux unités du même produit forment
un composant de quantité 2, non deux composants : `ProductBundle` refuse un SKU donné deux fois.

Un lot est immuable. Pour changer ses composants ou son prix, construisez-en un nouveau et enregistrez-le par-dessus
l'ancien ; les lignes déjà vendues gardent ce avec quoi elles ont été vendues.

## Les deux genres

| Genre | Vendu comme | Taxé | Retourné |
|---|---|---|---|
| `Assembled` | Un seul produit, sur une seule ligne | Sur son prix entier, par la catégorie de taxe du lot | En entier |
| `Collection` | Une seule ligne | Composant par composant, chacun à son propre taux sur sa part du prix | En entier |

`BundleKind::taxedAsOne()` distingue les deux. Une collection convient à un livre imprimé vendu avec son PDF, quand le
livre et le fichier peuvent être taxés différemment, ou à tous les titres d'un créateur. Un lot de l'un ou l'autre
genre est retourné en entier : un composant n'est jamais repris seul. Un rabais entre deux produits qui gardent chacun
leur ligne est une promotion, qui rembourse un produit à son prix moins le rabais.

## Stocker un lot

`toArray()` ne donne que des scalaires, pour une colonne JSON ou des colonnes distinctes ; `fromArray()` le relit et
refuse une ligne mal formée par `InvalidBundle` :

```php
[
    'sku' => 'STARTER',
    'kind' => 'assembled',
    'currency' => 'CAD',
    'components' => [['sku' => 'RULES', 'quantity' => 1], ['sku' => 'DICE', 'quantity' => 2]],
    'price' => ['fixed' => null, 'discount_ppm' => 150000, 'rounding' => 'HalfAwayFromZero'],
]
```

`Contract\BundleRepository::containing()` donne les SKU des lots qui contiennent un composant, en ordre et en nombre
borné, afin qu'un changement de prix, de stock ou de coût d'un composant puisse rafraîchir les lots qui le montrent.
