<!-- phil-dev source-sha256=00cd640f6d8b3564de2be7758b31e8edf8706d5c41abe4c02d4fae0fbe00c3c5 -->
# Rapports

Un navigateur qui rencontre une violation envoie un rapport à l'adresse que nomme la politique. Le bundle nomme son
propre point d'entrée, `phil_csp_report`, dans les deux directives de rapport et dans l'en-tête `Reporting-Endpoints` :

```http
Content-Security-Policy: default-src 'self'; report-uri /_csp/report; report-to csp
Reporting-Endpoints: csp="/_csp/report"
```

`report-uri` sert les navigateurs qui ne connaissent qu'elle ; `report-to` et `Reporting-Endpoints` servent la
Reporting API. L'adresse est le chemin de la route, que le navigateur résout par rapport à la page qu'il a chargée ;
`reporting.uri` en nomme une autre, un chemin du site ou une adresse `https`. Une politique avec `report: false` ne
porte aucune des deux directives.

## Où vont les rapports

L'en-tête `Host` d'une requête ne choisit jamais où vont ses rapports : un hôte falsifié n'envoie ni les rapports — ni
les adresses des pages qu'ils contiennent — ailleurs, ni son nom dans une réponse mise en cache. Avec `public_origin`,
l'adresse canonique du site, le chemin est posé sur elle :

```yaml
phil_browser_security:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://www.example.com
```

```http
Content-Security-Policy: default-src 'self'; report-uri https://www.example.com/_csp/report; report-to csp
Reporting-Endpoints: csp="https://www.example.com/_csp/report"
```

`phil/url` lit l'origine au démarrage du noyau : une adresse `http` ou `https` avec un nom d'hôte, sans adresse IP, sans
chemin, sans requête ni fragment ; `localhost` seulement en mode débogage. Régler `framework.trusted_hosts` à côté
refuse les hôtes falsifiés sur chaque page.

## Les deux formats

| Type de média | Corps | Lu comme |
|---|---|---|
| `application/csp-report` | `{"csp-report": {"document-uri": …, "effective-directive": …}}` | un rapport |
| `application/reports+json` | `[{"type": "csp-violation", "body": {"documentURL": …, "effectiveDirective": …}}, …]` | les rapports de type `csp-violation` ; les autres sont ignorés |
| `application/json` | l'une ou l'autre forme | comme sa forme l'indique |

De chaque rapport, le bundle garde la page, ce qui a été bloqué, la directive effective (ou le premier mot de la
directive violée), la disposition, le fichier source, la ligne et la colonne, le code de statut et l'échantillon. Il
laisse tomber le référent, la politique d'origine et l'agent utilisateur. Un rapport sans page ou sans directive valide
est écarté.

## La réponse

| Statut | Quand |
|---|---|
| `204` | Le corps a été lu ; ses rapports sont gardés. |
| `400` | Le corps n'est pas du JSON en huit niveaux au plus, ou pas dans la forme de son type de média. |
| `413` | Le corps est plus grand que `max_bytes`. |
| `415` | Le type de média n'est aucun des trois. |
| `429` | Le client a envoyé plus de corps que la limitation de débit ne le permet. |

La réponse est vide et `Cache-Control: no-store`. Les vérifications s'exécutent dans cet ordre : un corps du mauvais
type ou trop grand ne compte jamais dans la limitation de débit.

## Bornes

| Borne | Valeur |
|---|---|
| Un corps | `max_bytes`, 64 Kio par défaut ; le point d'entrée lit un octet de plus et s'arrête |
| La profondeur du JSON | 8 niveaux |
| Les rapports d'un corps | `max_reports`, 20 par défaut ; le reste est ignoré |
| Une adresse | 2 048 octets, coupée sur un caractère |
| Un échantillon | 40 caractères |
| Un nombre | 0 à 1 000 000 000 |

## Vie privée

- Une adresse perd ses identifiants, sa chaîne de requête et son fragment, où se cachent les jetons et les données
  personnelles : `https://user:secret@example.com/reset?token=abc#x` est gardée comme `https://example.com/reset`.
- Les caractères de contrôle sont retirés de chaque texte.
- L'adresse du client n'est jamais gardée en clair. La limitation de débit compte un HMAC-SHA-256 de celle-ci, avec le
  secret du noyau comme clé. Le magasin et l'événement la reçoivent tronquée par `phil/ip-anonymizer` (à un /24 en IPv4
  et un /48 en IPv6 par défaut), ou rien quand ce paquet manque ou que `client_address` est désactivé.

## La limitation de débit

Chaque client peut envoyer `limit` corps par `interval` (60 à la minute par défaut), comptés dans une fenêtre glissante
gardée dans `cache_pool`. La clé est toujours l'empreinte à clé de l'adresse. `symfony/rate-limiter` est nécessaire :
avec `rate_limit` activé, le conteneur ne se construit pas sans lui ; réglez `rate_limit: false` pour se passer de
limitation de débit. Le limiteur construit par le bundle réserve sous `lock.factory` quand il est défini —
`framework.lock` activé, avec symfony/lock —, comme FrameworkBundle : sans verrou, deux corps d'un même client qui
lisent la même fenêtre peuvent tous deux passer sa dernière place.

Dans l'environnement `prod`, la construction refuse aussi un `cache_pool` propre à chaque serveur, sur l'adaptateur
filesystem, APCu, array ou system, ce qu'est `cache.app` par défaut : nommez un pool sur Redis, Memcached ou une base de
données. La vérification est la garde de `phil/rate-limit`, partagée avec les autres bundles qui comptent dans un pool.

```yaml
framework:
    cache:
        pools:
            cache.rate_limit: { adapter: cache.adapter.redis }

phil_browser_security:
    reporting:
        rate_limit: { cache_pool: cache.rate_limit }
```

Quand phil/rate-limit-bundle est enregistré, les corps sont comptés par son contrat `Limiter`, par un limiteur nommé
`phil_browser_security.report` sur la même fenêtre : il est listé avec les autres de l'application, et décorer le service
`phil_browser_security.report_limit` le remplace. Déclarer un autre limiteur de ce nom fait échouer la construction.
Sans ce bundle, le `RateLimiterThrottle` du bundle compte directement sur `symfony/rate-limiter`.

`limiter` nomme une fabrique de limiteurs de l'application au lieu de la fenêtre construite ici ; son stockage est alors
celui de l'application :

```yaml
framework:
    rate_limiter:
        csp_reports: { policy: token_bucket, limit: 100, rate: { interval: '1 minute', amount: 20 } }

phil_browser_security:
    reporting:
        rate_limit: { limiter: limiter.csp_reports }
```

## Dédoublonnage et événement

Chaque rapport d'un corps est gardé une fois par empreinte : la page, ce qui a été bloqué, la directive, l'endroit dans
le code et la disposition. Un rapport répartit ensuite `ViolationReported`, sauf si la même empreinte a été annoncée
dans la fenêtre de dédoublonnage, une heure par défaut, gardée dans un pool PSR-6 partagé par les workers :

```php
use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AlertOnViolation
{
    public function __invoke(ViolationReported $event): void
    {
        // $event->report->documentUri, $event->report->effectiveDirective, $event->client
    }
}
```

## Le magasin

```yaml
phil_browser_security:
    reporting:
        storage: { enabled: true, connection: default, capacity: 10000, retention: 30 }
```

Chaque rapport ajoute un à la ligne de son empreinte dans `phil_csp_report`, en une instruction, ou crée la ligne ; deux
premiers rapports simultanés aboutissent à la même ligne. Au-delà de `capacity` lignes, une nouvelle violation n'est pas
gardée, tandis que les violations connues continuent de compter. `ReportRepository::latest()` lit les lignes, les plus
récemment vues d'abord.

```sh
bin/console phil:csp:purge-reports --older-than=30
```

retire les violations non signalées depuis autant de jours (la `retention` par défaut), pour que l'ordonnanceur
l'exécute chaque nuit.
