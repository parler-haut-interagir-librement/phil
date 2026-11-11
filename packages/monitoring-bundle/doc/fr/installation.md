<!-- phil-dev source-sha256=9f019b07b3e8fd2127fde7f783cb020d8f20e44f8109c6775c9768452b4ba7f6 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation`, `symfony/console` | `^8.2` | La configuration, les services, les points de terminaison, l'écouteur de la maintenance et les commandes. |
| `symfony/messenger`, `symfony/notifier` | `^8.2` | Les files d'attente, le message de veille et les alertes des tâches en retard. |
| `symfony/lock` | `^8.2` | Le verrou qui empêche deux rondes de l'interrupteur d'homme mort de signaler le même retard. |
| `symfony/filesystem`, `psr/cache` | | Le fichier indicateur et les stockages en cache. |
| `psr/clock`, `psr/log`, `psr/container` | | L'heure des vérifications et des battements, les échecs journalisés, les récepteurs des files d'attente. |
| `symfony/routing` | `^8.2` | Les routes des points de terminaison et de la page des files d'attente. |
| `doctrine/dbal` | `^4.5` | Les vérifications des connexions DBAL ; inutile sans `health.database`. |
| `league/flysystem` | `^3.30` | Les vérifications des stockages ; inutile sans `health.flysystem`. |
| `symfony/scheduler` | `^8.2` | Les battements des messages planifiés ; sans lui, les tâches cron émettent leur battement par la commande. |
| `twig/twig` | `^3.21` | La page de maintenance et la page des files d'attente ; sans lui, la réponse de maintenance est du texte brut. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | Les rôles du mode maintenance, ainsi que la page des files d'attente et ses actions. |

## Installer

```sh
composer require phil/monitoring-bundle
```

Enregistrez le bundle, si Symfony Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // ...
    Phil\MonitoringBundle\PhilMonitoringBundle::class => ['all' => true],
];
```

## Les routes

Le bundle ne déclare aucune route de lui-même. Importez les points de terminaison des moniteurs là où ils doivent
répondre, et la page des files d'attente derrière le pare-feu qui connaît le rôle qu'elle exige.

```yaml
# config/routes/phil_monitoring.yaml
phil_monitoring_health:
    resource: '@PhilMonitoringBundle/config/routes/health.php'
    prefix: /_health

phil_monitoring_queues:
    resource: '@PhilMonitoringBundle/config/routes/queues.php'
    prefix: /admin/queues
```

Les points de terminaison n'ont besoin ni de session ni d'utilisateur. Placez-les hors de tout pare-feu qui redirigerait
un moniteur vers une page de connexion, ou dans un pare-feu sans état.

## Les stockages

Les battements vivent dans `cache.app`, et l'indicateur de maintenance dans `var/maintenance.json`. Les deux ne tiennent
que si chaque *worker* et chaque serveur les partagent :

- sur un seul serveur, les valeurs par défaut conviennent, pourvu que `cache.app` ne soit pas un pool en tableau ou
  APCu ;
- sur plusieurs serveurs, donnez au bundle un pool sur un adaptateur partagé qui n'évince rien, et gardez-y la
  maintenance.

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.monitoring:
                adapter: cache.adapter.redis
```

```yaml
# config/packages/phil_monitoring.yaml
phil_monitoring:
    cache_pool: cache.monitoring
    maintenance:
        store: cache
```

Un pool qui évince perd des battements — la tâche se lit alors comme jamais exécutée, ce qui ne déclenche aucune
alerte — et met fin à une maintenance. Sur Valkey ou Redis, donnez à ce pool un serveur ou une base de données sans
politique d'éviction.
