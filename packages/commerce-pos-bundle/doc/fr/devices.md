<!-- phil-dev source-sha256=64bae6d25537d1b9e690c788a4afcbef74270dc634dad873db930c17559919f7 -->
# Caisses principale et mobile

Une boutique a une caisse principale par lieu — la tablette au comptoir, le centre de la boutique — et autant de
caisses mobiles qu'il y a de vendeurs avec un téléphone.

| | Caisse principale | Caisse mobile |
|---|---|---|
| Argent comptant et cartes sans NIP | Oui | Oui |
| Paiements qui demandent un NIP | Oui, sur ses terminaux à NIP | Non |
| Reprise d'une vente mise en attente par une autre caisse | Oui, une vente de son lieu | Seulement la sienne |
| Vente sans le réseau | Oui, par la file d'attente hors ligne | Non : elle attend le réseau |

```php
$registers->create('front', 'Counter', $cad, 5, location: 'store-1', role: RegisterRole::Main);
$registers->create('phone-ada', 'Ada\'s phone', $cad, 5, location: 'store-1');   // RegisterRole::Mobile by default
$registers->assign('front', RegisterRole::Main);
```

`Registers` garde une seule caisse principale active par lieu — les caisses qui ne nomment aucun lieu comptent pour un
seul lieu — et en refuse une seconde par `PosRefused`. Une caisse principale dotée d'un terminal qui prend les paiements
à NIP reste principale.

## Une vente commencée sur un téléphone, terminée au comptoir

Un vendeur construit le panier sur son téléphone et le met en attente ; la caisse principale du même lieu le reprend,
prend le paiement par NIP et l'enregistre :

```php
$checkout->hold($order, 'phone-ada', 'Red coat', 'ada', $clock->now());
$order = $checkout->resume($order->number, 'front');
```

Une vente en attente est reprise à la caisse qui l'a mise en attente, ou à la caisse principale de son lieu ; ailleurs,
`resume()` lève `PosRefused`. **Reprendre une vente exige le réseau** : le serveur garde le panier, et aucun réseau
local ne relie les appareils. La file d'attente hors ligne ne reprend jamais une vente non plus — une vente dont
l'identifiant est le numéro d'une commande existante est refusée ([offline.md](offline.md)).

## Le changement de caissier par NIP

Sur un appareil partagé que quelqu'un a déjà déverrouillé, un caissier choisit son nom et tape son NIP pour enregistrer
les ventes sous son nom. Le changement est **désactivé par défaut** ; activé avec un limiteur de débit
([installation.md](installation.md#configurer)), il exige `symfony/password-hasher` et `symfony/rate-limiter`.

```php
$cashierSwitch->setPin('ada', '2580', $clock->now());                   // 4 to 8 digits
$user = $cashierSwitch->switchTo('front', 'ada', '2580', $clock->now()); // 'ada', or CashierPinRefused
```

- Un NIP appartient à un utilisateur de l'application, nommé par son identifiant ; le changement ne tient aucun compte
  à lui et ne connecte personne. Il dit à l'application quel utilisateur a tapé le bon NIP ; l'application décide de ce
  que cet utilisateur peut faire, par la sécurité de Symfony, et demande sa preuve plus forte — une clé d'accès — avant
  un remboursement si elle le souhaite.
- Un NIP n'est gardé que sous forme d'empreinte de l'algorithme natif de PHP, par PasswordHasher de Symfony, et son
  empreinte est recalculée au prochain bon NIP quand le coût de l'algorithme a changé.
- Un chiffre répété (`1111`) et une suite (`1234`, `7890`, `9876`) sont refusés.
- Les tentatives sont comptées par le limiteur de débit, selon la caisse et l'utilisateur ; le bon NIP remet le compte à
  zéro. Au-delà de la limite, `CashierPinRefused::$retryAfter` dit quand réessayer.
- Un mauvais NIP et un utilisateur qui n'en a pas se lisent de la même façon, et prennent le même temps.
