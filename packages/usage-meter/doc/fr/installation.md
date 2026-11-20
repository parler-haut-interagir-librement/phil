<!-- phil-dev source-sha256=89ef214ea161da6ab68c7a8419269c9238ee8eb1d211874474910ccd19dbfd5d -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses |
| `psr/clock` | Le moment compté, tiré de l'horloge de l'application |
| `doctrine/dbal` 4.5, facultatif | `Dbal\DbalUsageStore`, les compteurs dans une base de données |
| `doctrine/orm` 3.7, facultatif | `Dbal\UsageSchemaListener`, les tables dans le schéma que génère l'ORM |
| `phil/commerce-subscription-bundle`, facultatif | `Subscription\FeatureLimits::fromSubscriptions()`, les limites tirées des fonctionnalités d'abonnement |

## Installer

```sh
composer require phil/usage-meter
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `UsageMeter` avec les compteurs de
l'application, un stockage et une horloge PSR-20 (le service `clock` de Symfony, ou toute autre), et gardez-le ; il ne
contient aucun état propre.

## Dans une application Symfony

`phil/usage-meter-bundle` déclare tout ce qui suit à partir de sa configuration, avec des limiteurs de débit sur les
contrôleurs et une purge en console. Sans le bundle, déclarez les compteurs, le stockage et le compteur d'utilisation
comme services. Avec Doctrine, le stockage utilise la connexion DBAL de l'application et l'écouteur ajoute ses tables
au schéma de cette connexion, pour que `doctrine:migrations:diff` écrive la migration qui les crée :

```yaml
services:
    app.meter.exports:
        class: Phil\UsageMeter\Meter
        arguments:
            $name: exports
            $period: !service { class: Phil\UsageMeter\Period\CalendarPeriod, factory: ['Phil\UsageMeter\Period\CalendarPeriod', 'month'], arguments: ['America/Toronto'] }
            $limit: !service { class: Phil\UsageMeter\Limit, factory: ['Phil\UsageMeter\Limit', 'of'], arguments: [3] }

    Phil\UsageMeter\Meters:
        arguments: ['@app.meter.exports']

    Phil\UsageMeter\Dbal\DbalUsageStore:
        arguments: ['@doctrine.dbal.default_connection']

    Phil\UsageMeter\Dbal\UsageSchemaListener:
        arguments: ['@doctrine.dbal.default_connection', '@Phil\UsageMeter\Dbal\DbalUsageStore']
        tags:
            - { name: doctrine.event_listener, event: postGenerateSchema }

    Phil\UsageMeter\UsageMeter:
        arguments:
            $meters: '@Phil\UsageMeter\Meters'
            $store: '@Phil\UsageMeter\Dbal\DbalUsageStore'
            $clock: '@clock'
            $limits: '@app.usage_limits'
```

Avec `phil/commerce-subscription-bundle`, les limites viennent des offres de l'abonné :

```yaml
services:
    app.usage_limits:
        class: Phil\UsageMeter\Subscription\FeatureLimits
        factory: ['Phil\UsageMeter\Subscription\FeatureLimits', 'fromSubscriptions']
        arguments: ['@Phil\CommerceSubscriptionBundle\Application\FeatureAccess']
```

Une tâche planifiée appelle `UsageMeter::purge()` (une fois par jour suffit) pour oublier les compteurs et les clés
qu'aucune fenêtre ne compte plus.
