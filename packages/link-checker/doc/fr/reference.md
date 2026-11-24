<!-- phil-dev source-sha256=35686cedfdff2090484084c63e1492a66836ff467aff31be8d6f4b72f716d7ed -->
<!-- phil-dev source-sha256=x -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes ne font pas partie de l'API publique.

## Vérification

| Type | Rôle |
|---|---|
| `LinkChecker` | Vérifie les liens de contenus et produit un `LinkReport` par contenu : `checkContent(ContentLinks)`, `check(iterable<ContentLinks>)`. Prend une sonde, un extracteur, un normalisateur, un cache, les pages internes, les hôtes internes et les deux durées de vie d'un résultat ; `MAX_TTL` vaut 30 jours. |
| `ContentLinks` | Un contenu à vérifier : `id`, `page` (un `Url` de phil/url, par rapport auquel les liens relatifs se résolvent), `html`. |
| `UrlNormalizer` | La clé sous laquelle deux liens ne sont vérifiés qu'une fois : `key(Url)`, sans le fragment, la requête triée seulement avec `sortQuery` ; `withoutFragment(Url)`, l'adresse demandée. |
| `LinkStatus` | `Alive`, `PermanentRedirect`, `TemporaryRedirect`, `Dead`, `MissingAnchor`, `Invalid`, `Review`, `Refused` ; `isBroken()` pour les trois premiers états cassés, `isRedirect()`. |
| `LinkResult` | Ce qu'a trouvé la vérification d'une adresse : `status`, `code`, `target`, `reason` ; `toArray()` et `fromArray()`, comme un cache le garde. |

## Extraction

| Type | Rôle |
|---|---|
| `Extraction\LinkExtractor` | Lit les liens du HTML avec `Dom\HTMLDocument` et les résout : `extract(string $html, Url $page)`. `maxBytes` (4 Mio, `MAX_BYTES` 64 Mio) et `maxLinks` (2 000, `MAX_LINKS` 100 000). |
| `Extraction\ExtractedLinks` | Les liens dans l'ordre du document, et `truncated` quand une limite a été atteinte. |
| `Extraction\Link` | Un lien : `href` tel qu'écrit, `url` résolue ou null, `element`, `attribute`, `text`, et `status` quand il est jugé sans requête (`Invalid`, `Refused`). `MAX_TEXT` vaut 200 caractères. |
| `Extraction\AnchorIndex` | Interne. Les `id` et les `a[name]` d'une page, pour ses fragments. |
| `Extraction\SrcsetParser` | Interne. Les adresses d'un `srcset`, selon les règles du standard HTML. |

## Requêtes

| Type | Rôle |
|---|---|
| `Probe\HttpLinkProbe` | Le `LinkProbe` sur phil/safe-http : `HEAD`, puis un `GET` sans corps après 405 ou 501, ou un `GET` qui lit le HTML sur demande ; les redirections et leur permanence ; `USER_AGENT` vaut `PhilLinkChecker/0.1`. Réinitialisable. |
| `Probe\ProbeResult` | Ce qu'a trouvé une sonde : `result`, le `html` de la page sur demande et s'il s'agit de HTML, et `complete` quand ce HTML a été lu jusqu'au bout. |
| `Probe\HopRecorder` | Interne. Le transport sous phil/safe-http qui garde les codes des redirections d'une vérification. |
| `Throttle\IntervalThrottle` | Un rythme par hôte en mémoire, sur une horloge de Symfony : `interval` (1 s, `MAX_INTERVAL` 60 s), `maxHosts` (1 000, `MAX_HOSTS` 100 000). Réinitialisable. |

## Ports

| Type | Rôle |
|---|---|
| `Contract\LinkProbe` | `probe(Url $url, bool $withBody = false): ProbeResult`. |
| `Contract\HostThrottle` | `wait(string $host): void`. |
| `Contract\InternalPages` | `html(Url $url): ?string`, le HTML d'une page du site sans requête, ou null. |
| `Contract\DeadLinkFixer` | `supports(LinkReport, CheckedLink): bool` et `fix(LinkReport, CheckedLink): void`. |

## Rapport

| Type | Rôle |
|---|---|
| `Report\LinkReport` | Les liens d'un contenu et leurs résultats : `broken()`, `toReview()`, `redirected()`, `refused()`, `isClean()`, `summary()`, `toText()`, `toJson()` ; `truncated`. |
| `Report\CheckedLink` | Un lien et son résultat ; `toText()`, une ligne d'un rapport, et sa forme JSON. |

## Interne

| Type | Rôle |
|---|---|
| `Memo` | Interne. Ce qu'un lot a trouvé, par clé, borné. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\LinkCheckerException` | Toute exception de la bibliothèque. |
| `Exception\InvalidOption` | Une option hors de ses bornes, ou un hôte interne qui n'est pas en ASCII minuscule. |

Une vérification ne lève elle-même aucune exception pour un lien : un échec du réseau est un état.
