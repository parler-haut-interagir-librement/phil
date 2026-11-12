<!-- phil-dev source-sha256=6e12be1061624e9d6ded52e5030e63f31248adbc49367e03c3c183c33ae02ed4 -->
# Surcharges

La configuration est globale ; une route, un contrôleur ou un service change n'importe quelle protection pour ses
propres réponses, avec les trois mêmes verbes : ajouter, remplacer, retirer.

## La politique de sécurité du contenu

Une route, un contrôleur ou un service change la politique de ses propres réponses, dans cet ordre : la valeur par
défaut `_csp` de la route, puis les attributs de la classe du contrôleur, puis ceux de sa méthode, puis les services,
chaque surcharge appliquée au résultat des précédentes.

| Champ | Effet |
|---|---|
| `policy` | Une autre politique nommée est appliquée au lieu de `enforce`. |
| `add` | Des sources ajoutées aux directives ; `'none'` leur cède la place ; une directive de récupération que la politique ne déclare pas part de `default-src`. |
| `replace` | Les sources des directives remplacées. |
| `remove` | Des directives retirées. |
| `reportOnly` (`report_only` sur une route) | `true` envoie la politique appliquée en `Content-Security-Policy-Report-Only`, `false` l'applique de nouveau. |
| `disabled` | `true` n'envoie aucune politique. |

`replace` passe avant `add`, et `remove` en dernier. `add`, `replace` et `remove` s'appliquent à la politique appliquée
et à la politique report-only de la même façon. Chaque source est vérifiée comme celles de la configuration : une source
invalide lève `InvalidPolicy` quand la réponse est écrite.

### L'attribut

```php
use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;

#[ContentSecurityPolicy(add: ['img-src' => ['https://images.example.com']])]
final class GalleryController
{
    #[ContentSecurityPolicy(policy: 'preview')]
    public function preview(): Response
    {
        // ...
    }
}
```

L'attribut est répétable, sur une classe et sur une méthode.

### La valeur par défaut de route

```yaml
# config/routes.yaml
map:
    path: /map
    controller: App\Controller\MapController
    defaults:
        _csp:
            add: { img-src: ['https://tiles.example.com'], connect-src: ['https://tiles.example.com'] }
```

`_csp` prend `policy`, `add`, `replace`, `remove`, `report_only` et `disabled` ; toute autre clé, ou une valeur du
mauvais type, lève `InvalidPolicy`.

### Depuis un service

```php
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;

final readonly class EmbedRenderer
{
    public function __construct(private ContentSecurityPolicyContext $csp)
    {
    }

    public function render(Embed $embed): string
    {
        $this->csp->override(new PolicyOverride(add: ['frame-src' => [$embed->origin()]]));

        return $embed->html();
    }
}
```

La surcharge va à la requête principale, donc un fragment rendu dans une sous-requête change la politique de sa page.

### Une réponse avec sa propre politique

Une réponse qui porte déjà `Content-Security-Policy` ou `Content-Security-Policy-Report-Only` quand elle quitte le
contrôleur la garde intacte, et ne reçoit aucun autre en-tête de politique.

## Chaque autre protection

`#[BrowserSecurity]`, la valeur par défaut de route `_browser_security` et `BrowserSecurityContext::override()` nomment
ce qu'ils changent par le nom de l'en-tête ou de la protection.

```php
use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;

#[BrowserSecurity(
    add: ['Permissions-Policy' => ['camera' => ['self']]],
    replace: ['X-Frame-Options' => 'DENY', 'Cross-Origin-Resource-Policy' => 'cross-origin'],
    remove: ['Cross-Origin-Embedder-Policy'],
)]
final class VideoCallController
```

```yaml
share:
    path: /share/{id}
    controller: App\Controller\ShareController
    defaults:
        _browser_security:
            replace: { Cross-Origin-Resource-Policy: cross-origin }
            remove: [X-Frame-Options]
```

| Nom | `add` | `replace` |
|---|---|---|
| `Content-Security-Policy` | des sources par directive | des sources par directive |
| `Permissions-Policy` | des origines par fonctionnalité | des origines par fonctionnalité |
| `Clear-Site-Data` | des directives | des directives |
| `Integrity-Policy`, `Integrity-Policy-Report-Only` | des destinations | des destinations |
| `signed-cookies` | des noms de cookies | des noms de cookies |
| `external-redirects` | des hôtes | des hôtes |
| `https-redirect` | non | `true` |
| `Strict-Transport-Security` | non | le texte de l'en-tête |
| les autres en-têtes de [la liste](headers.md) | non | une valeur de cet en-tête |

Chacun peut être retiré. Pour un nom donné, `replace` passe d'abord, puis `add`, puis `remove` ; chaque surcharge part de
la valeur de la précédente, et la première de la configuration. Un en-tête que la réponse porte déjà reste, sauf si une
surcharge le nomme : la surcharge l'emporte alors, et `remove` le retire.

L'ordre est le même que pour la politique : la route, la classe, la méthode, les services. `_browser_security` est lu
avant `_csp`. `signed-cookies` et `https-redirect` agissent avant que le contrôleur ne soit connu, donc seule une route
peut les changer : un attribut ou un service qui les nomme lève `InvalidPolicy`.

```php
use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;

$this->browserSecurity->override(new ProtectionOverride(remove: ['X-Frame-Options']));
```

Chaque nom et chaque valeur est vérifié à la lecture de la surcharge ; un nom inconnu lève `InvalidPolicy`.
