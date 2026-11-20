<!-- phil-dev source-sha256=b9ce1e384bc1ebbcf64befe088fe7eb2c44bdce2c21b5f152dcc6b62619ddf90 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute | Compte les caractères des noms, des libellés et des notes |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau : ses commandes, `OrderModifier`, ses paiements et `PaymentGateway`, et l'ajustement `Tip` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | L'argent comptant, les paiements, les rapports |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance des douze entités, et l'adaptateur du dépôt |
| `psr/clock` | ^1.0 | Le moment où une vente hors ligne est importée |
| `symfony/http-foundation` | ^8.2 | Les contrôleurs auxquels les caisses envoient les ventes hors ligne et les processeurs leurs notifications |
| `symfony/polyfill-php86` | ^1.37 | `SortDirection` de PHP, que les requêtes du dépôt passent à l'ORM, sur PHP 8.5 |
| `symfony/uid` | ^8.2 | Les identifiants des sessions et des mouvements |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |
| `@hotwired/stimulus` | ^3.0, celui de l'application | Le contrôleur de la file d'attente hors ligne |
| `symfony/password-hasher`, `symfony/rate-limiter` | ^8.1, ^8.2, suggérés | Le changement de caissier par NIP, quand il est activé |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | ^8.2, ^3.7, suggérés | Servir et enregistrer le contrôleur Stimulus |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-pos-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez le noyau et le module ; l'ordre des deux lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommercePosBundle\PhilCommercePosBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommercePosBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Chaque service lit le stockage du module ainsi que les commandes et les paiements du noyau : avec DoctrineBundle, le
module les enregistre avec son adaptateur Doctrine — générez une migration pour ses douze tables — ; sans lui, rien
n'est enregistré, et l'application déclare les services avec ses propres adaptateurs.

## Configurer

Chaque clé est facultative.

```yaml
# config/packages/phil_commerce_pos.yaml
phil_commerce_pos:
    recorded_methods: [debit, credit]   # methods taken on a terminal that is not connected; never "cash"
    tip_label: 'Tip'                    # how a tip reads on the receipt
    layaway_deposit: 200000             # the deposit of a layaway, in parts per million of the order
    cash_difference_threshold: 500      # the cash over or short, in minor units, the report flags; none by default
    terminals:
        method: card_present            # the core's payment method of the connected terminals
        fake: false                     # the fake adapter, for tests and development only
    cashier_pin:
        enabled: false                  # the cashier switch by PIN, off by default
        limiter: limiter.cashier_pin    # the rate limiter factory that bounds the attempts, required when enabled
```

Le changement de caissier exige un limiteur de débit que l'application déclare, avec `symfony/rate-limiter` :

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        cashier_pin:
            policy: fixed_window
            limit: 5
            interval: '15 minutes'
```

## Routage

Les caisses envoient leurs ventes hors ligne à une seule URL. Routez les requêtes `POST` vers le contrôleur, derrière
le contrôle d'accès du personnel :

```yaml
# config/routes/pos.yaml
pos_offline_sales:
    path: /register/offline-sales
    methods: [POST]
    controller: Phil\CommercePosBundle\Infrastructure\Http\OfflineSaleController
```

Les processeurs des terminaux connectés envoient leurs notifications à une autre, hors de toute connexion, puisque
chaque adaptateur vérifie la signature :

```yaml
pos_terminal_notifications:
    path: /register/terminal-notifications
    methods: [POST]
    controller: Phil\CommercePosBundle\Infrastructure\Http\TerminalNotificationController
```

## Le contrôleur Stimulus

Le contrôleur est fourni dans `assets/src/`, déclaré dans `assets/package.json` sous le nom `offline-queue`, chargé à la
demande. Avec AssetMapper et StimulusBundle, le module ajoute `assets/src/` aux chemins d'AssetMapper sous
`@phil/commerce-pos-bundle` ; activez le paquet dans `assets/controllers.json`, comme l'écrit Symfony Flex :

```json
{
    "controllers": {
        "@phil/commerce-pos-bundle": {
            "offline-queue": {"enabled": true, "fetch": "lazy"}
        }
    }
}
```

Il se nomme alors `phil--commerce-pos-bundle--offline-queue`. Voir [offline.md](offline.md).
