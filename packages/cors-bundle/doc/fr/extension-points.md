<!-- phil-dev source-sha256=f97d857dcf11596f80657598167cfc350164ad9f063f09374d97a41f19a35d45 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Choisir autrement la politique d'une requête : par hôte, par locataire, depuis une base de données | Implémentez `Phil\CorsBundle\Application\PolicyResolver` et créez un alias de l'interface vers votre service |
| Garder les chemins configurés et y ajouter | Décorez `phil_cors.path_policy_resolver`, en l'appelant quand votre propre règle n'a pas de réponse |
| Construire une politique en code | `new CorsPolicy(...)`, qui vérifie ses valeurs comme la configuration et lève `InvalidCorsPolicy` |
| Répondre vous-même à un préambule ou décorer une réponse | Injectez `phil_cors.headers` (`CorsHeaders`) et appelez `preflight()` ou `apply()` |

Un résolveur par hôte, pour une application qui sert plusieurs sites :

```php
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Application\PolicyResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class HostPolicyResolver implements PolicyResolver
{
    private CorsPolicy $shop;

    public function __construct(private PolicyResolver $paths)
    {
        $this->shop = new CorsPolicy(['https://shop.example.com'], allowMethods: ['GET', 'POST'], allowCredentials: true);
    }

    public function resolve(Request $request): ?CorsPolicy
    {
        if ('api.shop.example.com' === $request->getHost()) {
            return $this->shop;
        }

        return $this->paths->resolve($request);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Cors\HostPolicyResolver:
        decorates: phil_cors.path_policy_resolver
        arguments: ['@.inner']
```

Un résolveur est appelé deux fois pour une requête, une fois sur `kernel.request` pour un préambule et une fois sur
`kernel.response` sinon : il ne garde aucun état entre les requêtes, et construit ses politiques une seule fois, dans
son constructeur ou comme services, puisque en construire une compile ses motifs.

Les services n'ont aucun état : ils ne contiennent que la configuration, la même pour chaque requête d'un *worker* de
longue durée.
