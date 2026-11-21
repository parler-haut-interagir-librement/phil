<!-- phil-dev source-sha256=4430171f1666c6dd6c05630f6622b0e85962294bc4f1c0e654e2b13ad01332fc -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute | Compte les caractères des détenteurs et des notes |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau sur lequel le module se branche : ses commandes, ses paiements, `SellableResolver` et `PaymentGateway` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Les soldes et les mouvements |
| `phil/paged-media` | ^0.1@dev | La carte-cadeau imprimée |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance des instruments et des mouvements, et l'adaptateur du dépôt |
| `doctrine/dbal` | ^4.5 | La violation de contrainte d'unicité que l'adaptateur intercepte quand une autre requête a émis d'abord les cartes d'une commande |
| `doctrine/event-manager` | ^2.1 | Les écouteurs Doctrine de l'application, donnés au gestionnaire d'entités qui stocke les cartes d'une commande |
| `psr/clock` | ^1.0 | Le moment où un remboursement rend la valeur |
| `symfony/uid` | ^8.2 | Les identifiants des instruments et des mouvements |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-stored-value-bundle
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
    Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceStoredValueBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configurer

Chaque clé est facultative.

```yaml
# config/packages/phil_commerce_stored_value.yaml
phil_commerce_stored_value:
    gift_card:
        label: 'Gift card'        # how a gift card line reads, before its value
        tax_category: exempt      # selling a gift card is, in most places, not taxed
        max_value: 100000         # the highest value sold, in minor units
```

Donnez au noyau un taux de taxe pour cette catégorie, à zéro là où la vente d'une carte-cadeau n'est pas taxée.

## Stockage

Les services lisent un dépôt : avec DoctrineBundle, le module les enregistre avec son adaptateur Doctrine — générez une
migration pour ses deux tables — ; sans lui, seul le vendable de carte-cadeau est enregistré, et l'application déclare
le reste avec son propre adaptateur.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Le module ne fournit aucune migration : l'application la génère à partir de la correspondance, comme pour ses propres
entités.

### Passer de la version 0.1

La table des mouvements gagne deux colonnes nullables et deux contraintes d'unicité, qui empêchent qu'une valeur soit
créée deux fois ([Valeur créée une seule fois](stored-value.md#valeur-créée-une-seule-fois)) : `issued_unit`, le rang
d'une carte parmi les cartes qu'a émises sa commande, et `idempotency_key`. `doctrine:migrations:diff` écrit la
migration ; elle revient à ce qui suit.

MySQL et MariaDB :

```sql
ALTER TABLE phil_commerce_stored_value_movement
    ADD issued_unit INT DEFAULT NULL,
    ADD idempotency_key VARCHAR(128) DEFAULT NULL;
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_issued_unit
    ON phil_commerce_stored_value_movement (order_number, kind, issued_unit);
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_idempotency_key
    ON phil_commerce_stored_value_movement (instrument, idempotency_key);
```

PostgreSQL :

```sql
ALTER TABLE phil_commerce_stored_value_movement ADD issued_unit INT DEFAULT NULL;
ALTER TABLE phil_commerce_stored_value_movement ADD idempotency_key VARCHAR(128) DEFAULT NULL;
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_issued_unit
    ON phil_commerce_stored_value_movement (order_number, kind, issued_unit);
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_idempotency_key
    ON phil_commerce_stored_value_movement (instrument, idempotency_key);
```

Les lignes existantes gardent les deux colonnes vides. Un index unique laisse un nombre quelconque de lignes laisser
vide une colonne qui en fait partie, sur MySQL, MariaDB, PostgreSQL et SQLite, si bien que les cartes émises avant la
mise à niveau, et les mouvements sans clé, n'entrent jamais en collision ; les cartes qu'une commande émet ensuite
prennent les rangs qui suivent ceux qu'elle avait déjà.
