<!-- phil-dev source-sha256=d579d6535656dc12067d1332ea00ab60415f802f22b4c452dde6d0cc3a6cd3b0 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Sur un site

| Pour | Faites |
|---|---|
| Écrire les réglages poussés | Implémentez `Application\Contract\ConfigurationApplier` et nommez le service dans `agent.configuration_applier`. |
| Exécuter une mise à jour | Implémentez `Application\Contract\UpdateRunner` et nommez le service dans `agent.update_runner`. |
| Rapporter la santé d'ailleurs | Créez un alias de `Application\Contract\SiteHealth` vers votre propre service, avec `agent.health: none`. |
| Rapporter les paquets d'ailleurs | Créez un alias de `Application\Contract\InstalledPackages` vers votre propre service. |
| Garder les nonces ailleurs | Créez un alias de `Application\Contract\NonceStore` vers votre propre service, ou nommez un pool de cache dans `agent.cache_pool`. Retenir un nonce doit être une seule étape atomique, et un nonce non retenu doit lancer `StateNotKept`. |
| Garder les clés de la console ailleurs | Créez un alias de `Application\Contract\ConsoleKeyStore` vers votre propre service, ou nommez un pool de cache dans `agent.key_pool` ; il doit être partagé par les serveurs et survivre aux déploiements, et lancer `StateNotKept` quand il ne conserve pas un état. |

Un applicateur applique tous les réglages ou lève une exception ; il est appelé une fois par poussée. Un exécuteur
retourne un `Outcome` — réussi ou échoué, avec un court message pour le rapport — ou lève une exception. Ni l'un ni
l'autre ne garde d'état entre deux requêtes.

## Sur la console

| Pour | Faites |
|---|---|
| Être avisé d'une alerte | Écoutez `Application\Event\SiteAlertRaised`, répartie une fois par nouvelle alerte d'un site interrogé. |
| Interroger, pousser ou mettre à jour depuis l'application | Appelez `Application\Console\Puller::pull()`, `ConfigurationPushes::create()` et `deliver()`, `UpdatePlans::plan()` et `run()` (services publics), ou répartissez `PullSite`, `DeliverConfigurationPush` et `RunUpdatePlan`. |
| Enregistrer des sites depuis l'application | Appelez `Application\Console\SiteRegistry::register()` et `remove()` (publics). |
| Faire la rotation de la clé de la console depuis l'application | Appelez `Application\Console\KeyRotations::run()` (public), qui s'arrête au premier échec et reprend quand on l'appelle de nouveau. |
| Lire la flotte | Appelez `Application\Console\Fleet::overview()`, `site()` et `matrix()` (publics). |
| Garder les lignes de la console ailleurs | Créez des alias des dépôts de `Domain\Contract` vers vos propres services. |
| Joindre les agents autrement | Créez un alias de `Application\Contract\AgentClient` vers votre propre service, ou nommez un client HTTP dans `console.http_client`. |
| Dessiner les écrans autrement | Remplacez les gabarits `@PhilSitesConsole/*.html.twig` dans `templates/bundles/PhilSitesConsoleBundle/`. |
| Mettre les écrans dans la mise en page du site | Donnez à `console.layout` un gabarit avec les blocs `title` et `content`. |
| Ajouter une langue | Ajoutez `translations/phil_sites_console.<locale>.php` avec les clés du fichier anglais. |

Un `AgentClient` vérifie ce qu'il retourne ou lève `AgentFailure` : la console garde ce qu'elle reçoit comme la vérité
du site.
