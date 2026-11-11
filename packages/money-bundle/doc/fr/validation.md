<!-- phil-dev source-sha256=f9428f949c8fc0e7303255b5c5be080d719c698cb310eb8351ee9c2a34e36f48 -->
# Validation

Les deux contraintes acceptent `null`, comme toutes les contraintes sauf `NotNull` ; ajoutez `NotNull` pour un montant
requis.

## `AcceptedCurrency`

La valeur est dans une devise de `phil_money.currencies`. Elle accepte un `Money`, un `Currency` ou un code de devise ;
un code que ne connaissent pas les données des devises est une violation, pas une exception.

```php
#[AcceptedCurrency]
public ?Money $price = null;

#[AcceptedCurrency(message: 'We do not bill in {{ currency }}.')]
public ?string $billingCurrency = null;
```

| Option | Valeur par défaut |
|---|---|
| `message` | `The currency {{ currency }} is not accepted.` ; en français : `La devise {{ currency }} n’est pas acceptée.` |

Code : `AcceptedCurrency::NOT_ACCEPTED_ERROR`. Avec une liste `currencies` vide, toute devise connue est acceptée.

## `MoneyRange`

Le `Money` se situe entre `min` et `max`, bornes comprises, les décimaux étant lus **dans la devise propre du
montant** : `min: '0'` refuse un montant négatif dans toute devise ; `max: '500'` le plafonne à 500 unités de sa devise,
quelle qu'elle soit. Au moins une borne est donnée.

```php
#[MoneyRange(min: '0.01')]
public ?Money $price = null;

#[MoneyRange(min: '0', max: '10000', maxMessage: 'Call us for amounts above {{ limit }}.')]
public ?Money $deposit = null;
```

| Option | Valeur par défaut |
|---|---|
| `min`, `max` | `null` |
| `minMessage` | `This amount should be {{ limit }} or more.` ; en français : `Ce montant doit être d’au moins {{ limit }}.` |
| `maxMessage` | `This amount should be {{ limit }} or less.` ; en français : `Ce montant doit être d’au plus {{ limit }}.` |

Une borne est un décimal, `-?\d+(\.\d+)?` ; toute autre valeur lance `ConstraintDefinitionException` à la construction
de la contrainte. Une borne plus fine que la devise du montant est arrondie vers l'intérieur de l'intervalle : vers le
haut pour `min`, vers le bas pour `max`. Ainsi, `min: '0.01'` sur un montant en yens demande 1 yen, et `max: '9.99'`
permet 9. Une borne au-delà de tout montant que la devise peut contenir refuse tous les montants quand c'est un minimum
au-dessus d'eux ou un maximum au-dessous d'eux, et n'en refuse aucun sinon. Aucune borne ne lance d'exception pour un
montant qu'un utilisateur soumet.

`{{ limit }}` est la borne sous forme de décimal, arrondie dans la devise du montant, suivi d'un code : `0.00 CAD`,
`1 JPY`. Codes : `MoneyRange::TOO_LOW_ERROR`, `MoneyRange::TOO_HIGH_ERROR`.

Les messages sont dans le domaine `validators`, dans `translations/validators.en.php` et `validators.fr.php`.
