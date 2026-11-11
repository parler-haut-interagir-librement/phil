<!-- phil-dev source-sha256=f2523cc8905d5f6537f28eedd7089af1430686bce9d7ed2f2bb57f2aa496a13c -->
# phil/monitoring-bundle

**La surveillance d'un site Symfony : sa santé, un mode maintenance, les battements de cœur de ses tâches planifiées et
ses files d'attente Messenger.**

phil/monitoring-bundle réunit dans un seul paquet ce qu'un exploitant surveille sur un site en production. Les
vérifications de santé — une connexion DBAL, un pool de cache sur Valkey ou Redis, un transport Messenger, un
répertoire local, un stockage Flysystem, ou tout service de l'application qui implémente `HealthCheck` — tournent
derrière les points de terminaison des moniteurs de disponibilité externes : vivacité, disponibilité, et une vue
détaillée pour le porteur d'un jeton. Un mode maintenance, activé depuis la console, répond `503` avec `Retry-After` à
tous, sauf aux adresses, aux rôles et aux routes autorisés. Chaque tâche planifiée émet un battement quand elle
s'exécute, depuis Symfony Scheduler ou depuis une tâche cron. Un interrupteur d'homme mort (*dead-man switch*) signale,
par Symfony Notifier, la tâche qui ne s'est pas exécutée dans sa fenêtre. Une page montre les transports Messenger et
leurs messages en échec, et permet de les relancer ou de les retirer, pour un rôle donné.

Il s'adresse à toute application Symfony qui tourne en production et que quelqu'un surveille. Un autre bundle — une
page d'état publique, un tableau de bord — le lit par `MonitoringStatus`, un modèle de lecture stable des
vérifications, des tâches et de la maintenance. Rien de ce que le bundle montre ne porte plus que des noms, des états,
des nombres et des moments : aucun secret, aucun hôte, aucun renseignement personnel.

Il est écrit de zéro, sur les composants que Symfony fournit déjà : HttpKernel et HttpFoundation pour les points de
terminaison et le mode maintenance, Messenger, Scheduler et Notifier pour les files d'attente et les tâches, Cache et
Filesystem pour les stockages, Console pour les commandes ; DBAL, Flysystem, Twig et Security lorsque l'application
les a.

## État

| Partie | État |
|---|---|
| Vérifications de santé : DBAL, pools de cache, transports Messenger, répertoires, Flysystem, celles de l'application | Implémenté et testé |
| Vivacité, disponibilité et une vue détaillée derrière un jeton, en JSON | Implémenté et testé |
| Mode maintenance : fichier indicateur ou clé de cache, `503` avec `Retry-After`, listes d'autorisation, commande de console | Implémenté et testé |
| Battements depuis Symfony Scheduler et depuis cron, interrupteur d'homme mort par Symfony Notifier | Implémenté et testé |
| La page des files d'attente Messenger : nombres, messages en échec, relance et retrait derrière un rôle | Implémenté et testé |
| `MonitoringStatus`, le modèle de lecture pour un autre bundle | Implémenté et testé |
| Composants de `phil/ux-bundle` dans les pages | Pas commencé : les pages étendent la mise en page de l'application |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les routes, les stockages. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_monitoring`. |
| [Vérifications de santé](doc/fr/health-checks.md) | Les vérifications du bundle, les points de terminaison des moniteurs, la vue détaillée. |
| [Mode maintenance](doc/fr/maintenance.md) | L'activer, les listes d'autorisation, la page que voient les visiteurs. |
| [Battements de cœur](doc/fr/heartbeats.md) | Déclarer les tâches, émettre un battement depuis Scheduler ou cron, l'interrupteur d'homme mort et ses alertes. |
| [Files d'attente Messenger](doc/fr/queues.md) | La page des files d'attente, son rôle, ses actions. |
| [Points d'extension](doc/fr/extension-points.md) | Une vérification à vous, les stockages, `MonitoringStatus`, les gabarits. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, étiquette, route, commande et gabarit du bundle. |

## Démarrage rapide

```sh
composer require phil/monitoring-bundle
```

```yaml
# config/packages/phil_monitoring.yaml
phil_monitoring:
    health:
        token: '%env(MONITORING_TOKEN)%'
        database: [default]
        cache_pools: [cache.app]
        messenger: { async: 100, failed: 0 }
    heartbeats:
        tasks:
            backup: { every: 86400, grace: 3600 }
```

```yaml
# config/routes/phil_monitoring.yaml
phil_monitoring_health:
    resource: '@PhilMonitoringBundle/config/routes/health.php'
    prefix: /_health
```

```sh
curl https://example.com/_health/ready
# {"status":"up"}
bin/console phil:monitoring:maintenance enable --retry-after=600
```

## Prérequis

PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent. `doctrine/dbal`, `league/flysystem`, `symfony/scheduler`,
`symfony/routing`, `twig/twig`, `symfony/security-core` et `symfony/security-csrf` — chacun seulement pour la partie
qui l'utilise.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
