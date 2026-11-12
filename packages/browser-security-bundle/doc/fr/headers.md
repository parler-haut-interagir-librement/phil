<!-- phil-dev source-sha256=d72ba02e81b065aa924945baaf12bb235d6b25b7787bb579e7e55344f7b7d949 -->
# En-têtes voisins

Les en-têtes qui accompagnent une politique de sécurité du contenu, chacun écrit seulement quand la configuration lui
donne une valeur et seulement quand la réponse ne le porte pas déjà. Chaque valeur est vérifiée à la construction du
conteneur ; une page change n'importe lequel d'entre eux par une
[surcharge](overrides.md#chaque-autre-protection).

```yaml
phil_browser_security:
    headers:
        permissions_policy:
            camera: []
            microphone: []
            geolocation: [self, 'https://maps.example.com']
            fullscreen: ['*']
        cross_origin_opener_policy: same-origin
        cross_origin_opener_policy_report_only: ~
        cross_origin_embedder_policy: credentialless
        cross_origin_embedder_policy_report_only: ~
        cross_origin_resource_policy: same-site
        referrer_policy: strict-origin-when-cross-origin
        content_type_options: nosniff
        permitted_cross_domain_policies: none
        origin_agent_cluster: '?1'
        frame_options:
            default: SAMEORIGIN
            paths:
                /embed: ~
                /admin: DENY
```

| En-tête | Valeurs | Par défaut |
|---|---|---|
| `Permissions-Policy` | les origines par fonctionnalité : `[]` pour aucune, `self`, `*`, ou `https://host[:port]` | aucun |
| `Cross-Origin-Opener-Policy` et son `-Report-Only` | `same-origin`, `same-origin-allow-popups`, `noopener-allow-popups`, `unsafe-none` | aucun |
| `Cross-Origin-Embedder-Policy` et son `-Report-Only` | `require-corp`, `credentialless`, `unsafe-none` | aucun |
| `Cross-Origin-Resource-Policy` | `same-site`, `same-origin`, `cross-origin` | `same-site` |
| `Referrer-Policy` | les huit valeurs de la spécification | `strict-origin-when-cross-origin` |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `X-Frame-Options` | `DENY`, `SAMEORIGIN` | `SAMEORIGIN` |
| `X-Permitted-Cross-Domain-Policies` | `none`, `master-only`, `by-content-type`, `by-ftp-filename`, `all`, `none-this-response` | `none` |
| `Origin-Agent-Cluster` | `?1`, `?0` | `?1` |
| `Integrity-Policy` et son `-Report-Only` | les destinations bloquées, `script` et `style` | désactivé |

`null` n'envoie rien. Un nom de fonctionnalité est en minuscules, chiffres et traits d'union ; toute autre valeur fait
échouer la construction du conteneur. `X-XSS-Protection` est laissé de côté exprès : l'auditeur qu'il contrôlait a
disparu des navigateurs, et l'en-tête ne pourrait qu'ouvrir des brèches.

`Cross-Origin-Embedder-Policy: require-corp` bloque chaque ressource d'une autre origine qui n'y consent pas : essayez
d'abord `credentialless`, ou envoyez sa forme `-Report-Only` pendant un temps.

## Les cadres par chemin

`frame_options.default` est la valeur de chaque chemin, et `frame_options.paths` la valeur par préfixe, à la frontière
d'un segment : `/embed` couvre `/embed` et `/embed/map`, pas `/embedded`. Le plus long préfixe l'emporte, et `null`
n'envoie rien. La directive `frame-ancestors` de la politique a préséance dans chaque navigateur qui la lit ; cet en-tête
s'adresse à ceux qui ne la lisent pas.

## Rapports

Quand les rapports sont activés, les formes report-only des politiques d'origine croisée nomment le point d'entrée du
bundle, et les deux formes de la politique d'intégrité aussi. Le point d'entrée garde leurs rapports avec les violations
de la politique, sous le nom de leur en-tête, dans `effective_directive`.

```http
Cross-Origin-Opener-Policy-Report-Only: same-origin; report-to="csp"
Integrity-Policy-Report-Only: blocked-destinations=(script), sources=(inline), endpoints=(csp)
Reporting-Endpoints: csp="https://www.example.com/_csp/report"
```

## Intégrité

`Integrity-Policy` refuse les scripts chargés sans attribut `integrity`, et il est désactivé par défaut : essayez
d'abord `integrity_policy_report_only`, puis activez `integrity_policy`.

```yaml
phil_browser_security:
    headers:
        integrity_policy_report_only:
            enabled: true
            blocked_destinations: [script]
```

## Effacer les données du site à la déconnexion

Symfony écrit `Clear-Site-Data` avec l'option `clear_site_data` du `logout` d'un pare-feu. Le bundle enregistre ce même
écouteur pour tous les pare-feu à la fois, avec ce que le navigateur oublie :

```yaml
phil_browser_security:
    clear_site_data:
        on_logout: [cookies, storage]   # [] for nothing
```

Les valeurs sont `*`, `cache`, `cookies`, `storage`, `clientHints`, `executionContexts`, `prefetchCache` et
`prerenderCache`. Un pare-feu qui définit sa propre option la garde. `storage` vide le stockage local de l'origine,
brouillon non envoyé compris, et `cache` peut être lent dans certains navigateurs. Une route qui nomme
`Clear-Site-Data` dans une surcharge, après la suppression d'un compte par exemple, l'envoie sur n'importe quelle
réponse. Le navigateur ne lit cet en-tête que sur HTTPS.

## Ce que Symfony fait déjà

Symfony ne pose aucun en-tête de réponse de cette liste sur une page : le bundle les écrit. Pour la déconnexion, il
réutilise `ClearSiteDataLogoutListener` de `symfony/security-http`, comme ci-dessus.
