<!-- phil-dev source-sha256=9230bda50a4165c7598c1c7b61a14d2dcd4723f18974a9f696f6a555f5a97ca0 -->
# Points d'extension

## Détecteurs de l'application

Un service qui implémente `Phil\RouteAccessCheck\Contract\AccessRuleDetector` reçoit par autoconfiguration
l'étiquette `phil_route_access_check.detector`. Il est interrogé sur les règles de chaque route, à côté des trois
détecteurs du bundle. Écrivez-en un pour une règle que la vérification ne peut pas voir : un voter qui garde un espace
de noms, un écouteur d'URL signées, une passerelle. L'interface, et un détecteur qui déclare aussi ses règles avant les
routes, sont documentés dans le `doc/extension-points.md` de la bibliothèque.

```php
namespace App\Security;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\RuleScope;

final readonly class SignedUrlDetector implements AccessRuleDetector
{
    public function detect(AnalysedRoute $route): iterable
    {
        if (true === $route->route->getOption('signed')) {
            yield new AccessRule('signed_url', AccessEffect::Protected, RuleScope::Route, 'a signed URL, checked by SignedUrlListener');
        }
    }
}
```

Un détecteur est un service des environnements `dev` et `test` : enregistrez-le sous `when@dev` et `when@test`, ou
dans un répertoire que le conteneur de production ne charge pas, pour qu'il ne coûte rien en production.

## Formats de l'application

Un service qui implémente `Phil\RouteAccessCheck\Contract\ReportFormatter` reçoit par autoconfiguration l'étiquette
`phil_route_access_check.formatter` ; son `name()`, qui lui est propre, devient une valeur de `--format`.

## Services et paramètres

| Identifiant | Ce que c'est |
|---|---|
| `Phil\RouteAccessCheck\RouteAccessChecker` | La vérification, sur les détecteurs étiquetés, la liste d'exclusion et le lecteur de routes. |
| `Phil\RouteAccessCheck\Security\SecurityMap` | Les pare-feu et les entrées, d'après le paramètre `phil_route_access_check.security_map`. |
| `Phil\RouteAccessCheck\ControllerLocator` | Les contrôleurs, avec le paramètre `phil_route_access_check.controller_classes`. |
| `phil_route_access_check.security_map` | Les pare-feu et les entrées `access_control` qu'a lus `SecurityMapPass`, tels que les prend `SecurityMap::fromArray()`. |
| `phil_route_access_check.controller_classes` | La classe de chaque service de contrôleur dont l'identifiant n'est pas sa classe. |

Décorer `SecurityMap` donne à la vérification une autre table de sécurité — une configuration que l'application garde
ailleurs.
