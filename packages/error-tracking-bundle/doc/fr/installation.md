<!-- phil-dev source-sha256=dd255371ae70c8b3563c83c8b570795dcfe7a9ce85a0c16c081f9ef9893a0dc9 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/error-tracking` | `^0.1` | Les événements, les empreintes, l'assainisseur, les groupes et le traqueur. |
| `phil/ip-anonymizer` | `^0.1` | L'adresse du client est tronquée avant que quoi que ce soit ne la garde. |
| `phil/url` | `^0.1` | Les adresses des pages des navigateurs et les origines configurées. |
| `phil/rate-limit` | `^0.1` | La garde qui refuse une limite de débit sans son composant, ou sur un pool propre à chaque serveur en `prod`. |
| `doctrine/dbal` | `^4.5` | Les groupes et les événements sont gardés par DBAL. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation` | `^8.2` | La configuration, les services et la capture des requêtes. |
| `symfony/notifier` | `^8.2` | Les alertes par clavardage. |
| `symfony/service-contracts`, `psr/log`, `psr/clock` | | La réinitialisation de la garde, le journal d'une capture ratée, l'horloge. |
| `doctrine/orm` | `^3.7` | Facultatif : les tables ajoutées au schéma que génère l'ORM. |
| `symfony/messenger` | `^8.2` | Facultatif : la capture des messages en échec et les alertes en file d'attente. |
| `symfony/console` | `^8.2` | Facultatif : la capture des commandes, la purge et la commande d'état. |
| `monolog/monolog` | `^3.0` | Facultatif : le gestionnaire de journal. |
| `symfony/rate-limiter` | `^8.2` | Les limitations de débit ; la construction échoue sans lui, sauf si chaque `rate_limit` vaut `false`. |
| `phil/monitoring-bundle` | `^0.1` | Facultatif : la vérification de santé du magasin. |
| `phil/notifications-bundle` | `^0.1` | Facultatif : les alertes à des destinataires ou à une audience. |
| `phil/rate-limit-bundle` | `^0.1` | Facultatif : les rapports des navigateurs comptés par un limiteur nommé. |
| `twig/twig`, `symfony/security-core`, `symfony/security-csrf`, `symfony/routing` | | Facultatif : les pages d'administration. |

## Installer

```sh
composer require phil/error-tracking-bundle
```

Enregistrez le bundle, si Symfony Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // ...
    Phil\ErrorTrackingBundle\PhilErrorTrackingBundle::class => ['all' => true],
];
```

## Les tables

Avec l'ORM, l'écouteur du schéma ajoute `phil_error_group` et `phil_error_event` au schéma de leur connexion :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les à partir d'`ErrorTables::configure()` : voir [Stockage](storage.md).

## Les routes

Les pages d'administration, quand `admin` est activé, sont importées sous un préfixe que choisit l'application,
derrière son pare-feu :

```yaml
# config/routes/phil_error_tracking.yaml
phil_error_tracking:
    resource: '@PhilErrorTrackingBundle/config/routes.php'
    prefix: /admin/errors
```

Le point de terminaison des navigateurs, quand `browser` est activé :

```yaml
phil_error_tracking_browser:
    resource: '@PhilErrorTrackingBundle/config/routes_browser.php'
```

## Le gestionnaire Monolog

Le gestionnaire est un service, `phil_error_tracking.monolog_handler` ; Monolog l'utilise dès qu'il est nommé comme
gestionnaire :

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        errors:
            type: service
            id: phil_error_tracking.monolog_handler
```

## Alertes et Messenger

Les alertes par clavardage sont mises en file d'attente par Messenger par défaut : routez
`Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup` vers un transport asynchrone, ou mettez `alerts.async`
à `false` pour les envoyer dans la requête.

## La version

Les régressions se reconnaissent par version. Donnez au bundle la version ou le commit qui tourne, à partir de
l'environnement par exemple :

```yaml
phil_error_tracking:
    release: '%env(default::APP_RELEASE)%'
```

## Vérifier l'installation

```sh
bin/console phil:error-tracking:status
```

La commande liste les points de capture activés, dit si le magasin répond et combien de groupes il garde, et où vont
les alertes. Un magasin qui ne répond pas la fait échouer ; des alertes sans destination sont un avertissement, l'état
dégradé des [Alertes](alerts.md), et elle réussit quand même.
