<!-- phil-dev source-sha256=c36eba5dad4c98d0104b27496d79ff0b6ef7254765f9a48fbaeff8dda7cf37f3 -->
<!-- phil-dev source-sha256=0 -->
# Messages

## L'enveloppe

Chaque message est une requête Standard Webhooks : un corps JSON et trois en-têtes.

```http
POST /syndication/tea-house HTTP/1.1
content-type: application/json
webhook-id: msg_7Lz2Wq4Yf3sQbH8nT1xKpR
webhook-timestamp: 1793786400
webhook-signature: v1a,Zm9v…

{"type":"product.updated","timestamp":"2026-11-03T10:00:00.123456+00:00","audience":"corner-shop","data":{…}}
```

| Partie | Sens |
|---|---|
| `webhook-id` | L'identifiant du message, `msg_` suivi d'un UUID v7 en Base58 ; le même à chaque tentative |
| `webhook-timestamp` | Le moment où cette tentative a été signée, en secondes Unix |
| `webhook-signature` | La signature Ed25519, `v1a,…`, de l'identifiant, de l'horodatage et du corps ; `v1a` nomme l'algorithme et la version du schéma de signature |
| `type` | Ce que dit le message |
| `timestamp` | Le moment du changement, à la microseconde : un message plus récent décrit son sujet après un plus ancien |
| `audience` | Le code du partenaire auquel le message est adressé |
| `data` | Le contenu du message, toujours un objet JSON |

## `product.updated`

D'un fournisseur à un revendeur : un produit tel qu'il est maintenant.

| Champ | Type | Sens |
|---|---|---|
| `sku` | chaîne | De 1 à 64 caractères ASCII visibles |
| `name` | chaîne | De 1 à 255 caractères |
| `currency` | chaîne | Code ISO 4217 |
| `retail` | entier | Le prix de vente, en unités mineures |
| `cost` | entier | Ce que coûte une unité à ce revendeur, en unités mineures ; de zéro à `retail` |
| `tax_category` | chaîne | La catégorie à laquelle s'appliquent les taxes du revendeur |
| `available` | entier | Les unités disponibles ; 0 arrête la vente |
| `active` | booléen | `false` une fois le produit retiré |

## `order.forwarded`

D'un revendeur à un fournisseur : une commande à expédier.

| Champ | Type | Sens |
|---|---|---|
| `order` | chaîne | Le numéro de la commande chez le revendeur, de 1 à 64 caractères ASCII visibles |
| `currency` | chaîne | Code ISO 4217 |
| `lines` | liste | Le `sku`, la `quantity` (1 ou plus) et le `unit_cost` (unités mineures) de chaque ligne : un coût que le fournisseur a publié à ce revendeur, en vigueur dans sa période de grâce |
| `ship_to` | objet | Où expédier, dans la forme convenue entre les partenaires ; effacé de la boîte d'envoi de l'expéditeur une fois livré ou abandonné |

## `shipment.sent`

D'un fournisseur à un revendeur : un colis d'une commande que le revendeur a transmise est parti.

| Champ | Type | Sens |
|---|---|---|
| `order` | chaîne | Le numéro de la commande chez le revendeur |
| `shipment` | chaîne | L'identifiant du colis chez le fournisseur, de 1 à 64 caractères ASCII visibles |
| `carrier` | chaîne ou null | De 1 à 64 caractères ASCII imprimables |
| `tracking` | chaîne ou null | De 1 à 128 caractères ASCII visibles |
| `lines` | liste | Le `sku` et la `quantity` (1 ou plus) de chaque ligne ; de 1 à 500 UGS, aucune au-delà de ce que la commande contient |

## `return.requested`

D'un revendeur à un fournisseur : des marchandises d'une commande transmise sont en route de retour.

| Champ | Type | Sens |
|---|---|---|
| `order` | chaîne | Le numéro de la commande chez le revendeur |
| `return` | chaîne | Le numéro du retour chez le revendeur, de 1 à 64 caractères ASCII visibles |
| `currency` | chaîne | Le code ISO 4217 de la commande |
| `carrier`, `tracking` | chaîne ou null | Le colis renvoyé |
| `lines` | liste | Le `sku` et la `quantity` de chaque ligne ; jamais plus que ce que la commande contient moins les retours antérieurs |

## `return.received`

D'un fournisseur à un revendeur : les marchandises d'un retour sont arrivées, et ce qui a été accepté est crédité.

| Champ | Type | Sens |
|---|---|---|
| `order` | chaîne | Le numéro de la commande chez le revendeur |
| `return` | chaîne | Le numéro du retour |
| `currency` | chaîne | Code ISO 4217 |
| `credit` | entier | Le crédit, en unités mineures : chaque unité acceptée au coût unitaire de l'écriture de la commande ; le revendeur refuse un crédit que son propre registre ne donne pas |
| `lines` | liste | Le `sku` et la `quantity` acceptée de chaque ligne ; jamais plus que ce qui a été annoncé |

Aucun de ces messages ne porte de renseignements personnels.

## Autres types

Un message d'un type que le module ne connaît pas est accusé et ignoré : un partenaire dans une version plus récente
peut en envoyer davantage.
