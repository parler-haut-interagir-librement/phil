<!-- phil-dev source-sha256=b16b7873c281c1e8a452609d68e05c7a03eabcffa47c89f7095d43305d087bd5 -->
# Rabais manuels

Un employé baisse un prix à la main, au comptoir ou dans l'arrière-boutique, avec une raison : un montant fixe ou une
part, sur une ligne ou sur toute la commande. Le noyau garde le rabais ; un point de vente le montre.

```php
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;

$modifier->grantManualDiscount($order, ManualDiscount::amount('box', $twoDollars, 'Damaged box', 'employee-7', $diceLine));
$modifier->grantManualDiscount($order, ManualDiscount::share('regular', 1_000, 'Regular customer', 'employee-7')); // 10 % off the order
$modifier->withdrawManualDiscount($order, 'box');
```

- **Un rabais a une clé**, jusqu'à 32 lettres, chiffres, `_` ou `-`, unique dans sa commande, pour le retirer ; une
  raison sur une seule ligne ; et l'identifiant stable de qui l'a accordé.
- **Une part est en points de base**, de 1 à 10 000, arrondie vers le bas à l'unité mineure ; un montant est positif,
  dans la devise de la commande.
- **Seul un panier prend un rabais**, comme tout changement de ses lignes.
- **`ManualDiscountProcessor` transforme les rabais en ajustements `Promotion`** d'origine `manual:<key>` et de libellé
  la raison, après les options et les promotions du catalogue et avant les taxes, et laisse les autres promotions
  tranquilles. Une ligne est taxée une fois rabaissée.
- **Un rabais sur toute la commande est partagé entre ses lignes** en proportion de ce qui reste de chacune, par
  `Money::allocate()`, si bien que chaque ligne est taxée sur son propre montant rabaissé.
- **Un rabais ne prend jamais plus que ce à quoi il s'applique** : ce qui reste de la ligne une fois ses options et ses
  promotions et rabais antérieurs appliqués, jamais sous zéro.

Les rabais sont stockés avec la commande, dans la colonne JSON `manual_discounts`. Décider si un employé peut accorder
un rabais, et jusqu'à combien, relève de l'application, par ses voters de sécurité.
