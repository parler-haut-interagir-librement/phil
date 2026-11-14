<!-- phil-dev source-sha256=de273e680e8d3acd83c467f45712e6438473d5ea891ac3044130de43c0585031 -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le plancher déclaré. `hash_hmac()`, `hash_equals()` et `random_bytes()` font partie de chaque construction de PHP. |
| `phil/ddd-bundle` | `^0.1@dev` | `Salt` et `Commitment` lèvent son `Phil\DddBundle\Domain\Exception\InvalidValue` : une application attrape donc les erreurs de valeur du bundle d'audit avec le même `DomainError` que les siennes. |
| `psr/clock` | `^1.0` | Le moment où une ligne est consignée et où une ancre est prise; le service `clock` du conteneur, que FrameworkBundle enregistre dès que `symfony/clock` est installé. |
| `symfony/dependency-injection` | `^8.2` | La classe du bundle étend `AbstractBundle`. |
| `symfony/config` | `^8.2` | Importé nulle part dans le bundle, mais `AbstractBundle` implémente une interface de `symfony/config`, que `symfony/dependency-injection` n'exige qu'en développement : sans lui, la classe du bundle ne peut pas se charger quand le bundle est installé seul. |

En option, chacun n'est enregistré que lorsqu'il est installé ou configuré ([integrations.md](integrations.md)) :

| Paquet | Pour |
|---|---|
| `doctrine/dbal`, `doctrine/orm` | La table de la piste, son écouteur de schéma et le lecteur du journal |
| `symfony/console` | `phil:audit:anchor`, `phil:audit:verify` et `phil:audit:feed` |
| `symfony/messenger` | L'envoi de chaque ligne consignée, `dispatch_bus` |
| `symfony/security-core` | Le compte courant dans les lignes que le bundle consigne |
| `symfony/workflow` | Les transitions des sujets `Auditable`, `workflow_segment` |
| `symfony/http-client`, `phil/url` | Les faits envoyés à un collecteur OpenTelemetry, `feed.otlp`; son point de terminaison lu par `phil/url` |
| `phil/tenancy-bundle` | Le locataire dont chaque ligne est estampillée, et les lectures qui y sont limitées |

## Installer

```bash
composer require phil/audit-bundle:^0.1@dev
```

Le paquet déclare `"minimum-stability": "dev"` et un alias de branche `dev-main` → `0.1.x-dev`. Tant que
`phil/ddd-bundle` n'est pas publié, une application résout les deux à partir d'un dépôt qu'elle déclare (un dépôt
`path` ou `vcs`).

## Enregistrer le bundle

Avec Symfony Flex, le bundle est enregistré automatiquement. Sinon, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
    Phil\AuditBundle\PhilAuditBundle::class => ['all' => true],
];
```

Une fois enregistré, il câble la piste sur une connexion Doctrine, son lecteur et les commandes, comme
[configuration.md](configuration.md) le liste. Les objets-valeurs et les ports sont des classes simples que vous pouvez
utiliser sans enregistrer le bundle du tout.

## Lisez ceci avant de l'utiliser en production

La piste résiste à un administrateur qui a accès à la base de données **seulement quand ses ancres sont écrites dans un
stockage en ajout seul que cet administrateur ne peut pas réécrire**. Sans ancres, une réécriture complète de la chaîne
passe inaperçue. Voir [anchoring.md](anchoring.md).

La sérialisation canonique est **à vous d'écrire et de figer avant votre première ligne ancrée**. Voir
[serialization-door.md](serialization-door.md).
