<!-- phil-dev source-sha256=aae2c4544a8b6d58db5e13479532a7f5aebd1118edd2041d9d2498be447841c0 -->
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
trouve une carte que par l'empreinte de son code — un code n'est jamais gardé en clair.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `gift_card.label` | Le libellé d'une ligne de carte-cadeau dans une commande |
| `gift_card.tax_category` | La catégorie de taxe d'une carte-cadeau vendue |
| `gift_card.max_value` | La valeur la plus élevée à laquelle une carte-cadeau est vendue, en unités mineures |

## Services

`GiftCards`, `StoreCredits` et `StoredValuePayments` émettent, rechargent, dépensent et lisent ; publics — voir
[Valeur stockée](stored-value.md).
