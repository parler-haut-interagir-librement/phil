<!-- phil-dev source-sha256=df4a5694377b0161062329e5489000726e48c198803e5d447ae45cff969bd78b -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application implémente, configure ou utilise. Les
ports du noyau et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette |
|---|---|---|
| `SellableResolver` | `GiftCardSellables`, pour le type `gift_card` | `phil_commerce.sellable_resolver` |
| `PaymentGateway` | `StoredValueGateway`, pour les méthodes `gift_card` et `store_credit` | `phil_commerce.payment_gateway` |

Voir [Portée](scope.md).

## `StoreCreditHolders`

`Phil\CommerceStoredValueBundle\Application\Port\StoreCreditHolders` — qui est le client d'une commande, pour le crédit
en magasin : le module connaît une carte-cadeau par son code, pas par la personne qui la détient. Enregistré sous
l'interface, il est injecté automatiquement dans `StoredValueGateway` ; sans lui, un remboursement qui ne peut pas
retourner à sa carte — annulée, expirée — est refusé.

| Méthode | Contrat |
|---|---|
| `holderOf(string $orderNumber): ?string` | Le client, nommé comme `StoreCredits` le nomme, ou `null` quand la commande n'en a aucun de connu |

## Dépôts

`InstrumentRepository` est un alias public de `DoctrineStoredValueRepository` ; un stockage propre à l'application ne
trouve une carte que par l'empreinte de son code — un code n'est jamais gardé en clair. Il empêche aussi qu'une valeur soit créée deux fois :

| Méthode | Contrat |
|---|---|
| `addIssuedCards(array $cards): bool` | Stocke d'un coup, en entier ou pas du tout, les cartes qu'une commande émet avec leurs mouvements `Issued` ; retourne `false`, sans rien stocker, quand une carte de la même commande et du même rang (`Movement::$issuedUnit`) est déjà stockée — une vérification que le stockage rend atomique, par une contrainte d'unicité ou un verrou, jamais en lisant d'abord |
| `movementUnderKey(string $instrument, string $idempotencyKey): ?Movement` | Le mouvement qu'un instrument a enregistré sous cette clé ; le stockage en refuse un second |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `gift_card.label` | Le libellé d'une ligne de carte-cadeau dans une commande |
| `gift_card.tax_category` | La catégorie de taxe d'une carte-cadeau vendue |
| `gift_card.max_value` | La valeur la plus élevée à laquelle une carte-cadeau est vendue, en unités mineures |

## Services

`GiftCards`, `StoreCredits` et `StoredValuePayments` émettent, rechargent, dépensent et lisent ; publics — voir
[Valeur stockée](stored-value.md).
