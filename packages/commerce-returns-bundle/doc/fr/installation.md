<!-- phil-dev source-sha256=75ac53742bcae944458e4e196cf5d2ff6eca41c5c6435fa7b379851104881914 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute | Compte les caractères d'une note de refus |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau : ses commandes, ses paiements et `PaymentGateway` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | La valeur d'un retour, les remboursements |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | Les attributs de correspondance d'un retour et d'une note de crédit, et l'adaptateur du dépôt |
| `symfony/uid` | ^8.2 | Les identifiants des retours |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |
| `symfony/event-dispatcher-contracts` | ^3.6 | Les événements de chaque étape, quand un répartiteur est là |
| `symfony/translation-contracts` | ^3.6 | Les mots du bordereau, quand un traducteur est là |
| `phil/commerce-stored-value-bundle` | suggéré | Le règlement d'un retour en crédit en magasin |
| `phil/commerce-catalog-bundle` | suggéré | Les ensembles : une partie d'un produit composé refusée |
| `phil/commerce-promotions-bundle` | suggéré | Les promotions spéciales : le rabais d'un prix de lot repris |
| `phil/commerce-logistics-bundle` | suggéré | Le délai compté depuis la dernière expédition de la commande |
| `phil/paged-media` | suggéré | Les bordereaux de retour produits en PDF |
| `symfony/rate-limiter` | suggéré | Les retours que chaque client demande, bornés |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-returns-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez le noyau et le module ; l'ordre des lignes n'a pas d'importance. Enregistrez aussi
`phil/commerce-stored-value-bundle` pour le crédit en magasin, `phil/commerce-catalog-bundle` pour les ensembles,
`phil/commerce-promotions-bundle` pour les promotions spéciales et `phil/commerce-logistics-bundle` pour les dates
d'expédition : le module branche alors chacun de lui-même.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceReturnsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Les services lisent le stockage du module ainsi que les commandes et les paiements du noyau : avec DoctrineBundle, le
module les enregistre avec son adaptateur Doctrine — générez une migration pour ses tables `phil_commerce_return` et
`phil_commerce_return_credit_note` — ; sans lui, rien n'est enregistré.

## Configurer

```yaml
# config/packages/phil_commerce_returns.yaml
phil_commerce_returns:
    window_days: 30               # how long goods may come back
    window_from: placed           # placed, or shipped: from the order's last shipment
    restocking_fee: 0             # kept on the lines the shop is not at fault for, in parts per million
    delivery_refund: shops_fault  # never, shops_fault or whole_order
    expiry_days: ~                # how long an authorized return waits for its goods; ~ for ever
    rate_limiter: ~               # a symfony/rate-limiter factory bounding each customer's requests
    logistics_restocking:
        enabled: false            # resellable units back in the stock of phil/commerce-logistics-bundle
        location: ~               # the location every return goes to; ~ for the one each SKU left from
    slips:
        prefix: 'RS-'
        digits: 8
```

Une limite de débit nomme une fabrique de `framework.rate_limiter` :

```yaml
framework:
    rate_limiter:
        returns:
            policy: sliding_window
            limit: 5
            interval: '1 day'

phil_commerce_returns:
    rate_limiter: limiter.returns
```

L'expiration s'exécute depuis une tâche planifiée — un message de Symfony Scheduler ou une commande lancée par *cron* —
qui appelle `Returns::expire($clock->now())` et valide.

## Remise en stock

Avec `phil/commerce-logistics-bundle` 0.1.1 ou plus récent enregistré, activez `logistics_restocking` pour remettre les
unités revendables dans son stock ; sinon, implémentez `Restocking` pour les remettre sur la tablette propre à
l'application — l'autoconfiguration étiquette le service. Voyez [Retours](returns.md#remise-en-stock).
