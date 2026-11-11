<!-- phil-dev source-sha256=fb568cda0f92bc73335f4ca80e2c613357c81d0b23b01e678a1b62d4d610498d -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les services que déclare la configuration, les environnements hors desquels rien n'est
enregistré, un détecteur et un format de l'application autoconfigurés dans un conteneur compilé ; `SecurityMapPass`
sur les services que laisse SecurityBundle avant les passes d'optimisation — table des pare-feu, configurations des
pare-feu, table d'accès, correspondants de requête, expressions, paramètres, alias, correspondants sur mesure —; et la
commande : ses formats, ses codes de sortie, sa ligne de base générée, lue et refusée, son mode strict, sa sortie
d'erreur.

La suite `tests/Functional` démarre un noyau avec FrameworkBundle, SecurityBundle et le bundle, route des contrôleurs
par leurs attributs, et lance la commande : les pare-feu et les entrées `access_control` d'une vraie configuration de
sécurité atteignent la vérification, paramètres résolus, et rien n'est enregistré en `prod`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/route-access-check-bundle/tests
```

## La vérification comme test de l'application

La commande est l'étape d'intégration continue ; la même vérification s'exécute aussi comme test, avec le service de
l'environnement `test` :

```php
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RouteAccessTest extends KernelTestCase
{
    public function testEveryRouteHasAnAccessRule(): void
    {
        $container = self::getContainer();
        $report = $container->get(RouteAccessChecker::class)->check($container->get('router')->getRouteCollection());

        self::assertSame([], array_map(static fn (RouteVerdict $verdict): string => $verdict->route->name.': '.$verdict->problem(), $report->failures()));
    }
}
```
