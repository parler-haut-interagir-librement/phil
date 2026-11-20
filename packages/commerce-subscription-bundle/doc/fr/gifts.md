<!-- phil-dev source-sha256=1b0b96f817d8e6f70ae4835f6240a669aa75b6d12047ff77ad3d4d1c1a5b2736 -->
# Cadeaux

Un cadeau est un abonnement offert à quelqu'un d'autre. **L'acheteur paie** une ou plusieurs périodes d'une offre, par
une commande du noyau ; le paiement émet un **code**, et la personne qui l'échange démarre un abonnement.

```php
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;

// The buyer offers three months
$gift = $gifts->buy($buyerId, 'pro', 3, GiftRenewal::EndsAtTerm, $clock->now());
$order = $gifts->order($gift, $orderNumbers->next(), $taxZoneOf($buyerId), $clock->now());
$orders->add($order);                                      // the core's OrderRepository
[$payment, $start] = $payments->start($order, 'card', $clock->now());

// When the core applied the payment's notification
$code = $gifts->paid($gift, $order->number, $clock->now());   // shown once: hand it over now
$gifts->paymentFailed($gift, $order->number);                  // or wait for another order

// The beneficiary redeems it, typed or from a link
$subscription = $gifts->redeem($code, $beneficiaryId, $clock->now());
```

## Acheter et payer

- **`buy(buyer, offerCode, periods, renewal, at)`** enregistre un cadeau de 1 à 36 périodes d'une offre payante en
  vente. Une offre gratuite ou retirée est refusée.
- **`order(gift, number, taxZone, at)`** passe une commande du noyau avec une ligne `gift:<id>`, une unité par
  période, au prix actuel de l'offre, taxée par le noyau dans la zone de l'acheteur, et l'enregistre comme attendue.
- **`paid(gift, orderNumber, at)`** rend le cadeau échangeable pendant `gift_redeem_within_days` (365 par défaut) et
  retourne son code. **Le code est montré une seule fois** : le module n'en garde que l'empreinte SHA-256.
  L'application le remet sur-le-champ — par courriel, ou dans un lien qu'elle construit — et ne le met jamais dans un
  message d'une file d'attente.
- **`paymentFailed(gift, orderNumber)`** laisse le cadeau attendre une autre commande.
- **`reissueCode(gift)`** remplace un code perdu ; l'ancien ne permet plus d'échanger le cadeau.
- **`cancel(gift, at)`** abandonne un cadeau pas encore échangé, ou expiré ; rembourser ce qui a été payé relève du
  paiement.

## Le code

Un code compte 16 caractères de la base 32 de Crockford — 80 bits aléatoires —, montrés en quatre groupes :
`7KQ2-M9XD-4TPA-ZR6B`. Il est lu tel que tapé : en majuscules ou en minuscules, avec ou sans tirets et espaces, `O` lu
comme `0`, `I` et `L` comme `1`.

**Un code inconnu, mal formé, déjà échangé, remplacé ou expiré est refusé de la même façon**, `This code redeems no
gift.`, pour que rien ne distingue une tentative au hasard d'un code qui a déjà existé. Un code mal formé ne lit rien.
Les tentatives sont à limiter par l'application, avec le limiteur de débit de Symfony sur la route d'échange.

## Le bénéficiaire

**L'acheteur et le bénéficiaire sont désignés par un identifiant stable** : l'identifiant immuable de leur compte, ou
la revendication `sub` de leur fournisseur d'identité — **jamais l'identifiant avec lequel ils se connectent**, une
adresse courriel ou un nom d'utilisateur, qu'ils peuvent changer et qu'une autre personne peut prendre après eux.
`StableIdentifier` refuse tout ce qui contient `@`.

`redeem(code, beneficiary, at)` démarre l'abonnement du bénéficiaire, actif sur-le-champ pour les périodes payées,
quel que soit l'essai de l'offre, même si l'offre a été retirée depuis. Son `periodAmount()` est la part d'une période
de ce que l'acheteur a payé, à partir de laquelle un changement de palier ultérieur crédite.

## Quand les périodes payées sont écoulées

| `GiftRenewal` | L'abonnement |
|---|---|
| `BuyerPays` | Se renouvelle comme tout abonnement : le balayage le retourne, et ses renouvellements sont commandés pour que l'acheteur les paie — l'acheteur est son `payer()` |
| `EndsAtTerm` | Est annulé à la fin des périodes payées ; le bénéficiaire peut le reprendre, à ses frais |

## Stockage

Avec DoctrineBundle, un cadeau est une rangée de `phil_commerce_subscription_gift`.

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(36)`, clé primaire | Un UUID ordonné dans le temps (v7) |
| `buyer` | `VARCHAR(255)`, indexée | L'identifiant stable de l'acheteur |
| `offer`, `periods`, `renewal` | `VARCHAR(64)`, `SMALLINT`, `VARCHAR(16)` | Ce qui est offert, et ce qui se passe ensuite |
| `state` | `VARCHAR(16)` | En attente, payé, échangé ou annulé |
| `code_hash` | `CHAR(64)`, unique, nullable | L'empreinte SHA-256 du code actuel |
| `awaited_order` | `VARCHAR(64)`, nullable | La commande du noyau dont le paiement est attendu |
| `period_amount`, `currency` | `BIGINT`, `CHAR(3)` | La part d'une période de ce qui a été payé |
| `redeemable_until` | `DATETIME`, nullable | Le moment où le code cesse d'être valide |
| `beneficiary`, `subscription` | `VARCHAR(255)`, `CHAR(36)`, nullable | Qui l'a échangé, et l'abonnement qu'il a démarré |
| `created_at`, `closed_at` | `DATETIME` | Le moment de l'achat, et celui de l'échange ou de l'annulation |
| `version` | `INTEGER` | Avancée à chaque écriture |

Un abonnement démarré par un cadeau garde son acheteur dans la colonne `payer`, `VARCHAR(255)`, nullable, de
`phil_commerce_subscription`.
