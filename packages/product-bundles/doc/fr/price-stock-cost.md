<!-- phil-dev source-sha256=b8376bcf464c91dda1b1321690f0d9166245d334fca675df54978ab804b8f746 -->
# Prix, stock et coût

## Le prix

Une `PricePolicy` est l'une de deux :

| Politique | Prix |
|---|---|
| `PricePolicy::fixed(Money $price)` | Le prix donné, supérieur à zéro, quoi que les composants se vendent séparément. |
| `PricePolicy::discountOnComponents(Rate $discount, RoundingMode $rounding)` | La somme des prix des composants moins le taux, de 0 à 99,9999 %. |

Un rabais est un `Rate` de phil/pricing en parties par million — `Rate::ofPercent('15')` vaut 150 000 — et il est
arrondi à l'unité par le `\RoundingMode` donné, à la moitié en s'éloignant de zéro par défaut. Les mêmes composants
donnent toujours le même prix.

`BundlePricing::quote()` lit le prix unitaire de chaque composant par `Contract\ComponentPrices` et donne un
`BundleQuote` :

| Champ | Contenu |
|---|---|
| `componentsValue` | Ce que les composants se vendent séparément : chaque prix unitaire multiplié par ses unités. |
| `price` | Ce que le lot se vend. |
| `components` | Un `QuotedComponent` chacun : prix unitaire, valeur et part du prix. |
| `savings()` | La valeur moins le prix ; négative quand le lot se vend plus cher que ses composants. |

Les parts suivent les valeurs des composants : un livre de 40,00 et des dés de 10,00 dans un lot de 45,00 prennent
36,00 et 9,00. Elles sont réparties par `Money::allocate()`, donc elles s'additionnent au prix à l'unité près ; quand
chaque composant est gratuit séparément, elles suivent les quantités. Un composant sans prix dans la devise du lot, dans
une autre devise ou sous zéro lance `PriceUnknown` : le lot ne se vend pas à l'aveuglette.

## Le stock

`BundleAvailability::of()` lit les unités disponibles de chaque composant par `Contract\ComponentStock`, les divise par
les unités qu'un lot contient, arrondi à l'inférieur, et garde la plus petite : un `BundleStock` avec la quantité et le
composant qui la fixe, le premier en cas d'égalité. Un composant à zéro ou moins donne zéro lot.

## Le coût

`ComposedCost::of()` lit le coût unitaire de chaque composant par `Contract\ComponentCosts` et donne un `BundleCost` :
le coût unitaire de chaque composant et son coût pour un lot, et leur somme. Quand le coût d'un composant est inconnu,
la somme est nulle et `unknown` liste les composants en cause : un coût qui omet un composant serait trop bas.

Le lot n'a pas de coût à lui, puisqu'il n'a pas de stock à lui : son coût est toujours composé des coûts de ses
composants maintenant, selon la méthode qu'emploie l'évaluation des stocks — coût moyen pondéré ou premier entré,
premier sorti.

## La marge

`ComposedCost::margin()` prend un devis et un coût et donne un `BundleMargin` : le prix, le coût, la marge et son taux
sur le prix, arrondi à la moitié en s'éloignant de zéro sauf si un autre `\RoundingMode` est donné. Elle est nulle
quand le coût est inconnu ou dans une autre devise que le prix ; son taux est nul quand le prix est zéro.

`Bundles::margin()` fait les deux pour un SKU.
