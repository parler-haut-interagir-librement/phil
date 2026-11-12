<!-- phil-dev source-sha256=5cbe7b739607298301095b34e203dc84cd721d44d0cb944c5054b9b165f16cbe -->
# Points d'extension

| Point | Comment |
|---|---|
| La politique d'une réponse, depuis le code | `Application\ContentSecurityPolicyContext::override()` avec une `PolicyOverride` |
| Un nonce hors de Twig | `ContentSecurityPolicyContext::nonce('script')`, `nonce('style')`, `sharedNonce()` |
| Du code en ligne écrit à l'exécution | `ContentSecurityPolicyContext::allowInline('script', $code)` |
| La politique d'un contrôleur | `#[Application\Attribute\ContentSecurityPolicy]` sur sa classe ou sa méthode |
| La politique d'une route | Sa valeur par défaut `_csp` |
| N'importe quelle protection d'une réponse, depuis le code | `Application\BrowserSecurityContext::override()` avec une `ProtectionOverride` |
| N'importe quelle protection d'un contrôleur | `#[Application\Attribute\BrowserSecurity]` sur sa classe ou sa méthode |
| N'importe quelle protection d'une route | Sa valeur par défaut `_browser_security` |
| Un fichier téléversé par quelqu'un | `Application\UserContentPolicy::apply()`, `#[Application\Attribute\UserContent]` ou la valeur par défaut de route `_user_content` |
| Une violation signalée | Écouter `Application\Event\ViolationReported` |
| La limitation de débit des corps | Implémenter `Application\Contract\ReportThrottle`, nommer une fabrique de limiteurs dans `reporting.rate_limit.limiter`, ou décorer `phil_browser_security.report_limit` avec phil/rate-limit-bundle |
| Le dédoublonnage des violations | Implémenter `Application\Contract\ReportDeduplicator` |
| Ce qui est gardé de l'adresse du client | Implémenter `Application\Contract\ClientAddressAnonymizer` |
| L'endroit où les violations sont gardées | Implémenter `Domain\Contract\ReportRepository` et lui donner un alias |
| Lire les violations | `ReportRepository::latest()` |
| Les en-têtes hors d'une requête | `Application\HeaderBuilder::build()` avec un `RequestState` |
| Une signature hors de l'écouteur | `Application\CookieSigner` |
| Le panneau du profileur | Le gabarit `@PhilBrowserSecurity/profiler/collector.html.twig`, surchargé dans `templates/bundles/PhilBrowserSecurityBundle/` |
| L'horloge | L'option `clock` : le bundle ne lit jamais l'heure lui-même |

## Remplacer un port

Le récepteur prend ses collaborateurs en argument ; une application en remplace un en définissant l'argument de
`ReportReceiver` dans ses propres services :

```yaml
services:
    App\Csp\SaltedAddress: ~

    Phil\BrowserSecurityBundle\Application\ReportReceiver:
        arguments:
            $anonymizer: '@App\Csp\SaltedAddress'
```

```php
use Phil\BrowserSecurityBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;

final readonly class SaltedAddress implements ClientAddressAnonymizer
{
    public function __construct(private KeyedHash $hash)
    {
    }

    public function anonymize(string $address): ?string
    {
        $parsed = IpAddress::tryParse($address);

        return null === $parsed ? null : $this->hash->anonymize($parsed);
    }
}
```

Les invariants :

- un anonymiseur ne renvoie jamais l'adresse en clair ;
- une limitation de débit reçoit une empreinte à clé, jamais l'adresse, et compte le corps qu'elle accepte ;
- un dédoublonneur retient une empreinte quand il la dit nouvelle, pour une fenêtre partagée par les workers ;
- un dépôt compte une empreinte une fois par appel à `record()`, et ne garde aucune adresse en clair.
