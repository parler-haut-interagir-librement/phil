<!-- phil-dev source-sha256=85f8ef8ffb299142eed1372b4f54e96d0b52e29d2d09f2b463c0603426a68779 -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle` | Le bundle : la configuration, les services, la passe de compilation ; `DETECTOR_TAG`, `FORMATTER_TAG`, `SECURITY_MAP`, `CONTROLLER_CLASSES` |
| `Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass` | Lit les pare-feu, les entrées `access_control` et les classes de contrôleur dans des paramètres |
| `Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand` | `phil:route-access:check` ; `DEFAULT_BASELINE` |

## Services

| Identifiant | Classe |
|---|---|
| `Phil\RouteAccessCheck\RouteAccessChecker` | La vérification |
| `Phil\RouteAccessCheck\RouteReader`, `Phil\RouteAccessCheck\ControllerLocator`, `Phil\RouteAccessCheck\IgnoreList` | Ce avec quoi elle lit les routes, et ce qu'elle met de côté |
| `Phil\RouteAccessCheck\Security\SecurityMap` | La configuration de sécurité |
| `Phil\RouteAccessCheck\Detector\SecurityAttributeDetector`, `PublicRouteDetector`, `SecurityConfigDetector` | Les détecteurs, étiquetés |
| `Phil\RouteAccessCheck\Format\TextFormatter`, `JsonFormatter`, `GitHubFormatter`, `JunitFormatter` | Les formats, étiquetés |
| `Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand` | La commande, étiquetée `console.command` |

Tous n'existent que dans les environnements de `phil_route_access_check.environments`.

## Étiquettes

| Étiquette | Sur | Autoconfigurée à partir de |
|---|---|---|
| `phil_route_access_check.detector` | les détecteurs qu'interroge la vérification | `Phil\RouteAccessCheck\Contract\AccessRuleDetector` |
| `phil_route_access_check.formatter` | les formats de `--format` | `Phil\RouteAccessCheck\Contract\ReportFormatter` |

## Paramètres

| Paramètre | Valeur |
|---|---|
| `phil_route_access_check.security_map` | Les pare-feu et les entrées `access_control`, tels que les prend `SecurityMap::fromArray()` |
| `phil_route_access_check.controller_classes` | La classe de chaque service de contrôleur dont l'identifiant n'est pas sa classe |
