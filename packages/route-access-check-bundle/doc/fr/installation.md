<!-- phil-dev source-sha256=5b750211baa4ca70c069fc359590bd0840df94beed807bffe62b31a1c5ff039c -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/route-access-check` | `^0.1` | La vérification, ses détecteurs, ses rapports et sa ligne de base. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | La configuration et la passe de compilation. |
| `symfony/console` | `^8.2` | La commande. |
| `symfony/filesystem` | `^8.2` | Le fichier de ligne de base, écrit de façon atomique. |
| `symfony/http-foundation`, `symfony/routing` | `^8.2` | Les correspondants de requête relus, les routes du routeur. |
| `symfony/security-bundle` | `^8.2` | Les pare-feu et les entrées `access_control` ; sans lui, la vérification lit seulement les attributs et les options de route. |

## Installer

```sh
composer require --dev phil/route-access-check-bundle
```

Enregistrez le bundle pour les environnements où la vérification s'exécute, si Symfony Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // ...
    Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle::class => ['dev' => true, 'test' => true],
];
```

Le bundle se garde aussi lui-même : dans un environnement hors de `phil_route_access_check.environments`, il
n'enregistre aucun service ni aucun paramètre. Un conteneur de production construit avec le bundle installé n'en porte
rien.

## Quel environnement vérifier

Lancez la commande dans l'environnement dont la configuration de sécurité est celle à vérifier — d'habitude `test`,
que l'intégration continue construit de toute façon, ou `dev`. Un bloc `when@prod` de `security.yaml` n'est pas lu
depuis un autre environnement. Gardez les règles d'accès hors des blocs propres à un environnement, ou ajoutez `prod` à
`environments` pour une tâche d'intégration continue qui construit exprès le conteneur de production.
