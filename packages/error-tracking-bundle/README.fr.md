<!-- phil-dev source-sha256=31cc00b3af9b954f6ab158fe7bc19a31be3ff098ec91e9e4629c624f6147dfdf -->
# phil/error-tracking-bundle

**Les erreurs d'une application Symfony, capturées où qu'elles se produisent et gardées dans sa propre base de
données.**

phil/error-tracking-bundle apporte `phil/error-tracking` dans une application Symfony. L'exception sur laquelle se
termine une requête, un message qu'un *worker* n'a pas réussi à traiter, l'erreur sur laquelle se termine une commande
de console et les enregistrements d'un gestionnaire Monolog sont tous capturés par une même garde. Celle-ci ne lève
jamais d'exception, ne capture jamais pendant qu'une autre capture s'exécute (une base de données en panne ne peut pas
lancer une boucle) et ne prend qu'une fois le même throwable. Chaque événement est débarrassé des renseignements
personnels et des secrets, groupé par son empreinte et gardé par DBAL, avec un plafond d'événements par groupe et une
purge selon la durée de conservation.

Des pages d'administration, activées explicitement et ouvertes aux utilisateurs qui ont leur rôle, listent les groupes
par état et montrent chacun avec ses derniers événements et leurs traces, avec les formulaires qui le résolvent,
l'ignorent ou le rouvrent ; avec EasyAdmin, les mêmes pages sont greffées sur les tableaux de bord de l'interface
d'administration. Une alerte signale le premier événement d'un groupe et une régression : une notification à
des destinataires nommés avec phil/notifications-bundle, ou un message de clavardage par le *chatter* de
`symfony/notifier`, mis en file d'attente par Messenger par défaut. Avec phil/monitoring-bundle, une vérification de
santé dit si le magasin répond et si de nouveaux groupes arrivent en masse. Un point de terminaison facultatif reçoit
les erreurs non attrapées des navigateurs depuis un petit contrôleur Stimulus, vérifiées par origine et par jeton,
bornées en taille et limitées en débit par client.

Il s'adresse à toute application Symfony qui veut ses erreurs chez elle plutôt que chez un tiers : un site dans sa
première phase, une boutique, un outil qui réunit sa santé, ses erreurs et ses sauvegardes sur une seule page. Il repose
sur les composants que Symfony fournit déjà : HttpKernel, Messenger, Console et Monolog pour la capture,
DBAL pour le magasin, Twig, Security et CSRF pour les pages, Notifier pour les alertes, RateLimiter pour les
limitations de débit.

## État

| Partie | État |
|---|---|
| Capture des requêtes, des messages en échec, des commandes et des enregistrements Monolog, sans boucles | Implémenté et testé |
| Groupes et événements plafonnés par DBAL, instants en UTC, l'écouteur du schéma, la commande de purge | Implémenté et testé |
| Pages d'administration par rôle : liste, détail avec les traces, résoudre, ignorer, rouvrir | Implémenté et testé |
| Les mêmes pages dans une interface d'administration EasyAdmin, par `#[AdminRoute]`, et leur lien de menu | Implémenté et testé |
| Alertes pour les premiers événements et les régressions, par notification ou par clavardage, en file d'attente ou envoyées tout de suite | Implémenté et testé |
| L'état dégradé des alertes sans destination : le journal, les pages d'administration et `phil:error-tracking:status` | Implémenté et testé |
| La vérification de santé du magasin pour phil/monitoring-bundle | Implémenté et testé |
| Les rapports des navigateurs comptés par un limiteur nommé de phil/rate-limit-bundle | Implémenté et testé |
| Le point de terminaison des erreurs des navigateurs et son contrôleur Stimulus | Implémenté et testé, l'analyseur soumis au fuzzing, les tests du contrôleur exécutés avec les suites |
| Cartes de sources des scripts minifiés | Non commencé, prévu après la première version : un cadre du navigateur garde l'adresse et la ligne du script minifié |
| Réception du format d'enveloppe de Sentry | Non fourni, par choix : les points de capture sont l'interface, voir [L'enveloppe de Sentry](doc/fr/sentry-envelope.md) |
| Un guide depuis sentry-symfony | Écrit : [Migrer depuis sentry-symfony](doc/fr/migrating-from-sentry.md) |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables, les routes, le gestionnaire Monolog. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_error_tracking`. |
| [Capture](doc/fr/capture.md) | Chaque point de capture, ce qu'il étiquette, les arguments dans les messages, et la garde contre les boucles. |
| [Stockage](doc/fr/storage.md) | Les tables, le plafond d'événements, la purge. |
| [Administration](doc/fr/administration.md) | Les pages, le rôle, la mise en page, les actions, une interface d'administration EasyAdmin. |
| [Alertes](doc/fr/alerts.md) | Les notifications et les messages de clavardage, en file d'attente ou envoyés tout de suite, et l'état dégradé. |
| [Erreurs du navigateur](doc/fr/browser-errors.md) | Le point de terminaison, ses vérifications, et le contrôleur Stimulus. |
| [L'enveloppe de Sentry](doc/fr/sentry-envelope.md) | Pourquoi le point de terminaison ne lit pas le format de Sentry. |
| [Migrer depuis sentry-symfony](doc/fr/migrating-from-sentry.md) | Passer d'une application du SDK de Sentry aux points de capture, pas à pas. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, l'alerteur et les exceptions ignorées. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, la cible de fuzzing, et comment tester une application qui capture des erreurs. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, route, commande et gabarit du bundle. |

## Démarrage rapide

```sh
composer require phil/error-tracking-bundle
```

```yaml
# config/packages/phil_error_tracking.yaml
phil_error_tracking:
    release: '%env(default::APP_RELEASE)%'
    alerts:
        transport: discord
    admin: true
```

```yaml
# config/routes/phil_error_tracking.yaml
phil_error_tracking:
    resource: '@PhilErrorTrackingBundle/config/routes.php'
    prefix: /admin/errors
```

```sh
bin/console doctrine:migrations:diff     # the two tables of the bundle
bin/console doctrine:migrations:migrate
```

Dès lors, les exceptions des requêtes, les messages en échec et les erreurs des commandes sont capturés ; les groupes
sont listés à `/admin/errors` pour `ROLE_ADMIN`.

## Prérequis

PHP 8.5 ou plus récent, `phil/error-tracking`, `phil/ip-anonymizer`, `phil/url`, `phil/rate-limit`, Doctrine DBAL 4 et
Symfony 8.2 ; voir [Installation](doc/fr/installation.md) pour les paquets facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
