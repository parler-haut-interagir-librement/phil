<!-- phil-dev source-sha256=053284fda9b8fcb1442b41522ac63974d1777d7dd93635dc079a1f37d86c8922 -->
# phil/route-access-check-bundle

**`bin/console phil:route-access:check` : chaque route de l'application a une règle d'accès, sinon l'étape
d'intégration continue échoue.**

phil/route-access-check-bundle intègre `phil/route-access-check` à une application Symfony sous forme de commande
console. Il vérifie chaque route du routeur contre les règles qui la protègent — `#[IsGranted]`, les pare-feu et les
entrées `access_control` de la configuration de sécurité, et les déclarations publiques explicites — et se termine
avec le code 1 quand une route n'a aucune règle ou se contredit. Le rapport liste chaque route avec la règle trouvée :
en texte pour une personne, en JSON pour un outil, en annotations GitHub sur les contrôleurs, ou en JUnit pour un
serveur d'intégration continue.

Il s'adresse à toute application Symfony qui veut que la construction relise ses règles d'accès, plutôt que la
mémoire. La configuration de sécurité est lue dans les services que construit SecurityBundle, une fois tous les
fichiers de configuration fusionnés. La vérification voit donc exactement ce qu'exécute l'application : les blocs
`when@`, la configuration en PHP, les paramètres, les entrées `route:`. Il enregistre ses services dans les
environnements `dev` et `test` seulement : aucun écouteur, rien en production.

Il est écrit de zéro, sur les composants que Symfony fournit déjà : Config et DependencyInjection pour la
configuration et la passe de compilation, Console pour la commande, Filesystem pour le fichier de ligne de base.

## État

| Partie | État |
|---|---|
| La commande, ses quatre formats, sa ligne de base et son mode strict | Implémenté et testé |
| Pare-feu et `access_control` lus dans les services de SecurityBundle | Implémenté et testé, contre SecurityBundle dans un noyau |
| Services de contrôleur dont l'identifiant n'est pas leur classe | Implémenté et testé |
| Détecteurs et formats de l'application, autoconfigurés | Implémenté et testé |
| Rien d'enregistré hors des environnements configurés | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, enregistrer le bundle pour dev et test seulement. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_route_access_check`. |
| [La commande](doc/fr/command.md) | Ses options, ses codes de sortie, et une étape d'intégration continue. |
| [Points d'extension](doc/fr/extension-points.md) | Les détecteurs et les formats de l'application, les services et les paramètres. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et la vérification comme test de l'application. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, étiquette et paramètre du bundle. |

Les règles elles-mêmes — comment chaque source est lue, quand une route est publique, les états — sont documentées
avec la bibliothèque, dans `phil/route-access-check`.

## Démarrage rapide

```sh
composer require --dev phil/route-access-check-bundle
```

```php
// config/bundles.php
Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle::class => ['dev' => true, 'test' => true],
```

```sh
bin/console phil:route-access:check --env=test
bin/console phil:route-access:check --env=test --format=github --only-problems
bin/console phil:route-access:check --env=test --generate-baseline
```

## Prérequis

PHP 8.5 ou plus récent, `phil/route-access-check`, et Symfony 8.2 : Config, Console, DependencyInjection, Filesystem,
HttpFoundation et Routing. SecurityBundle est lu quand il est installé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
