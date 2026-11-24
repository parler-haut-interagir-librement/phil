<!-- phil-dev source-sha256=eb48a0c3fa958807537896a16a9d2af2b41af0f1b2ed235481681631e13b2fb2 -->
# Remises

`DiscountCascade::apply()` reçoit un prix et une liste de taux de remise, et applique chacun à ce que le précédent a
laissé, dans l'ordre donné :

```php
$sale = new DiscountCascade()->apply(Money::ofDecimal('100.00', $cad), [Rate::ofPercent('20'), Rate::ofPercent('10')]);

$sale->steps[0]->discount->toDecimal(); // "20.00", on 100.00
$sale->steps[1]->discount->toDecimal(); // "8.00", on 80.00
$sale->price->toDecimal();              // "72.00"
$sale->totalDiscount()->toDecimal();    // "28.00"
$sale->effectiveRate()->percent();      // "28", not 30
```

## Le résultat

`apply()` retourne un `DiscountedPrice` :

| Membre | Ce qu'il contient |
|---|---|
| `original` | Le prix donné |
| `steps` | Un `DiscountStep` par taux, dans l'ordre donné |
| `price` | Le prix après la dernière étape |
| `totalDiscount()` | Le prix d'origine moins le prix final : la somme des remises des étapes, exactement |
| `effectiveRate()` | La remise totale sur le prix d'origine, arrondie au plus proche en s'éloignant de zéro pour les demi-valeurs, sauf si un `\RoundingMode` est donné |

Chaque `DiscountStep` contient son `rate`, le prix `before` (avant), la `discount` qu'elle a retirée et le prix `after`
(après). Chaque étape part du `after` de la précédente ; un reçu peut imprimer chaque remise sur sa propre ligne.

## Arrondi

Chaque étape arrondit sa propre remise à l'unité, au plus proche en s'éloignant de zéro pour les demi-valeurs par
défaut, et l'arrondi est passé en troisième argument. Arrondir chaque étape est ce qu'affiche un reçu ; le prix final
peut différer d'une unité de celui calculé sur le taux combiné. L'ordre des taux est la décision de l'application et il
est conservé : avec l'arrondi, deux ordres peuvent donner des prix éloignés d'une unité.

Un prix nié — un remboursement — donne exactement les étapes niées.

## Ce qui est refusé

Un taux sous 0 % ou au-dessus de 100 % lève `InvalidRate` : une majoration n'est pas une remise. 100 % retire tout le
prix, et les étapes suivantes ne retirent rien. Une liste vide laisse le prix tel quel. Le taux effectif d'un prix nul
est indéfini et lève `UndefinedRate`.

Les remises à montant fixe, les règles de cumul et les conditions relèvent d'un moteur de promotions ; la cascade ne
fait qu'enchaîner des taux.
