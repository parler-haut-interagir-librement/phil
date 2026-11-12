<!-- phil-dev source-sha256=5d2d0c2333292c8e07e5f103e33d44f62dfb85f3eb8674d48fa7892ab59850c7 -->
# Collecte et admission

Comment une page vue atteint les compteurs, ou ne les atteint pas : le script qui l'envoie, le point de collecte qui la
reçoit, et chaque décision entre les deux.

| Partie | État |
|---|---|
| `Origin`, `CollectionSite`, `SiteStatus` | Implémenté et testé |
| `AdmissionRule`, `Admission` | Implémenté et testé, sur le `Verdict` de `phil/bot-detection-bundle` |
| `MeasureCounters` | Implémenté et testé : les compteurs qu'un envoi fait avancer ([measures.md](measures.md)) |
| `CollectionProjection`, `CollectionRateLimiter`, `CrawlerDetector`, `CountryResolver`, `CounterStore`, `OutboundLinkDirectory` | Contrats, chacun avec un adaptateur par défaut que l'application peut remplacer ([configuration.md](configuration.md#les-adaptateurs)) |
| `Collector`, `CollectionRequest`, `CollectionOutcome` | Implémenté et testé : l'ordre de la collecte, d'une requête aux compteurs |
| Le point de collecte, `CollectController` et la route `phil_analytics_collect` | Implémenté et testé |
| Le script de collecte (un appel par page, aucun témoin, aucun stockage local) | Un contrôleur Stimulus, testé avec Node |

## Le script de collecte

Avec StimulusBundle, le contrôleur est trouvé grâce au `assets/package.json` du bundle ; il se place sur le corps de la
page :

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

Chaque fois qu'il se connecte — la page se charge, ou Turbo affiche la suivante —, il envoie une page vue :

| Champ | Valeur |
|---|---|
| `path` | `location.pathname` : la requête est laissée de côté, elle peut porter un jeton |
| `referrer` | l'origine du référent seulement, sans chemin, requête ni fragment : celle de `document.referrer` pour la première page d'un document, celle de la page précédente pour une page que Turbo affiche ensuite ; `null` quand il n'y en a pas, ou qu'elle n'est pas `http(s)` |
| `verdict` | la valeur `verdict`, sinon le verdict que le contrôleur de `phil/bot-detection-bundle` a laissé sur l'élément racine (`data-phil-bot-verdict`), sinon `unknown` |
| `from` | le chemin de la page précédente du même site, quand il y en a une, pour la paire de routes |
| `dimensions` | la valeur `dimensions` que donne la page, quand elle en donne une |

La page signale ensuite ses événements et, quand on la quitte, jusqu'où elle a été lue : voir [measures.md](measures.md).

**Le référent est une origine, jamais un chemin.** Le serveur ne lit que l'origine du référent
([sources-and-referrers.md](sources-and-referrers.md)), et un chemin peut nommer une personne — un profil, un document
privé, une recherche —, alors le script ne l'envoie pas.

**Une navigation Turbo n'est pas une nouvelle visite.** Turbo Drive affiche la page suivante dans le même document, et
`document.referrer` garde le référent de la première page ; renvoyé, un visiteur arrivé d'un moteur de recherche qui
parcourt cinq pages compterait six visites venues de la recherche. Le contrôleur garde l'adresse de la dernière page
qu'il a vue, en mémoire, pour la durée du document, et envoie son origine — celle du site, classée interne — comme
référent de la page suivante.

Il n'envoie rien quand le navigateur envoie Do Not Track (`navigator.doNotTrack === '1'`) ou Global Privacy Control
(`navigator.globalPrivacyControl === true`), puisque le point de collecte ne compterait rien de toute façon. Il envoie
par un `fetch` maintenu en vie (`keepalive: true`), qui survit au départ de la page, **sans identifiants**
(`credentials: 'omit'`), pour qu'aucun témoin de l'origine de collecte n'accompagne une page vue ; en `text/plain`, pour
qu'un point de collecte d'une autre origine n'ait besoin d'aucune requête préalable. Seul un navigateur sans `fetch` se
rabat sur `navigator.sendBeacon()`, qui envoie toujours les témoins de l'origine de collecte : gardez cette origine
sans témoin. Il ne pose aucun témoin et n'écrit rien dans le stockage local ni de session : il n'y a rien à consentir.

## L'ordre de la collecte

L'ordre est fixe, et **chaque étape ne fait que restreindre** : rien de plus tard ne peut changer un refus en écriture.
Un corps plus long que ce que lit la collecte est refusé avant la première étape.

1. **Résoudre l'origine** que revendique la requête (`Origin::ofRequest()`). Aucune origine : refus sans écriture.
2. **Trouver le site qui la déclare** (`CollectionProjection::siteDeclaring()`). Aucun site : refus sans écriture.
3. **Vérifier l'état du site** (`SiteStatus::collects()`). Un site suspendu ou retiré est refusé **avant que le
   limiteur de débit soit consommé** : il ne dépense aucun budget et ne laisse aucune empreinte dans l'état du
   limiteur.
4. **Consommer le limiteur de débit** pour ce site et cette empreinte de client à clé
   ([rate-limiting.md](rate-limiting.md)). Une adresse de client qui n'est pas une adresse est refusée avant ; budget
   dépassé, ou limiteur indisponible : la page vue est abandonnée (fermé en cas d'échec).
5. **Décider de l'admission** (`AdmissionRule::decide()`), qui vérifie d'abord l'état de nouveau.
6. **Écrire** ce que l'admission permet : les compteurs d'audience, ou le compteur des verdicts robot, ou rien.

## À quel site appartient une requête : `Origin`

`Phil\AnalyticsBundle\Domain\ValueObject\Origin` est une origine web `scheme://host[:port]`, normalisée : schéma et
hôte en minuscules, port par défaut retiré. Seuls `http` et `https` sont des origines. La valeur est lue comme le fait
un navigateur, par l'analyseur d'URL WHATWG de PHP sur lequel repose `phil/url` : un hôte international sous sa forme
ASCII, comme les navigateurs l'envoient dans `Origin`, de sorte que `https://bücher.example` déclaré correspond à
l'en-tête `https://xn--bcher-kva.example` ; une adresse IPv6 entre crochets ; une barre oblique inverse dans un
`Referer` prise pour la barre oblique qui termine l'hôte. `localhost` et les adresses IP sont aussi des origines : ce
sont les origines déclarées qui décident lesquelles collectent.

```php
Origin::fromString('HTTPS://Example.COM:443')->toString();      // 'https://example.com'
Origin::ofUrl('https://example.com/a?code=secret#state');       // Origin https://example.com (path, query, fragment dropped)
Origin::ofUrl('android-app://com.example');                     // null
Origin::ofRequest('https://a.example', 'https://b.example/p');  // https://a.example — the Origin header wins
Origin::ofRequest('null', 'https://b.example/p');               // https://b.example — opaque Origin: falls back to Referer
Origin::ofRequest(null, 'https://b.example/p');                 // https://b.example
Origin::ofRequest('https://a.example/path', 'https://b.example/p'); // null — a malformed Origin is not rescued by the Referer
Origin::ofRequest(null, null);                                  // null — refuse
Origin::ofUrl('https://example.com@evil.example/');             // null — user information, never taken for the host
Origin::ofUrl('https://evil.example\\@example.com/');            // Origin https://evil.example, as a browser reads it
```

| Méthode | Comportement |
|---|---|
| `fromString(string)` | Forme stricte (déclarée par l'application, ou un en-tête `Origin`) : aucun chemin, pas même `/`, aucune requête, aucun fragment ni identifiant, même vide. Lance `InvalidValue` sinon |
| `ofUrl(string): ?Origin` | L'origine d'une URL complète comme un `Referer` ; `null` quand il n'y a pas d'origine http(s), ou que l'URL porte des identifiants, des espaces ou des caractères de contrôle |
| `ofRequest(?string $originHeader, ?string $referer): ?Origin` | L'en-tête `Origin` s'il est présent, non vide et pas `null`, lu strictement ; sinon l'origine du `Referer` ; sinon `null` |
| `equals(Origin)`, `toString()`, `__toString()` | Comparaison sur le schéma, l'hôte et le port ; `scheme://host[:port]` |
| Propriétés | `scheme`, `host`, `port` (`null` pour le port par défaut) |

Une requête forgée qui imite une origine déclarée reste possible : la vérification de l'origine n'est pas une
authentification. Le limiteur de débit, la détection des robots d'indexation, le verdict robot et la liste de pourriel
la bornent.

## Ce que la collecte sait d'un site : `CollectionSite`

`Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite` est la forme d'une entrée de la projection de collecte : un
site, ses origines déclarées, ses routes déclarées, son état, et rien d'autre — aucune information personnelle.

```php
$site = CollectionSite::of(
    SiteId::fromString('spring-con'),
    [Origin::fromString('https://example.com'), Origin::fromString('https://www.example.com')],
    DeclaredRoutes::of([DeclaredRoute::of('home', '/')]),
    SiteStatus::Active,
);
$site->declares(Origin::fromString('https://WWW.example.com')); // true
$site->declares(Origin::fromString('http://example.com'));      // false: another scheme is another origin
$site->site; $site->origins; $site->routes; $site->status;
```

`of()` refuse un site sans origine (aucune requête ne pourrait lui appartenir) et une origine déclarée deux fois
(`https://example.com` et `https://example.com:443` sont la même origine).

**La collecte lit cette projection, jamais l'application qui la tient à jour.** L'application écrit la projection quand
la déclaration d'un site change et quand un site est suspendu, rétabli ou retiré ; le point de collecte ne fait que la
lire, idéalement avec un rôle de base de données qui ne peut rien lire d'autre
([extension-points.md](extension-points.md#collectionprojection)).

## Si un site collecte : `SiteStatus`

| Cas | `collects()` | Sens |
|---|---|---|
| `SiteStatus::Active` | `true` | Collecté |
| `SiteStatus::Suspended` | `false` | Refusé jusqu'à son rétablissement |
| `SiteStatus::Retired` | `false` | Refusé pour de bon ; l'application supprime ses compteurs (`CounterStore::deleteSite()`) |

## Ce que fait une page vue : `AdmissionRule` et `Admission`

```php
$admission = new AdmissionRule()->decide(
    $site->status,                                  // SiteStatus
    PrivacySignals::fromHeaders($dnt, $secGpc),     // privacy-signals.md
    $crawlers->isDeclaredCrawler($userAgent),       // bool, from the CrawlerDetector port
    $classifier->classify($referrer, $site->origins), // ReferralKind, sources-and-referrers.md
    Verdict::fromSubmitted($submittedVerdict),      // from phil/bot-detection-bundle
);
```

`decide()` retourne le premier cas qui correspond, dans cet ordre :

| Ordre | Condition | `Admission` | `isRefusal()` | `advancesAudience()` | `advancesRobotVerdictCounter()` |
|---|---|---|---|---|---|
| 1 | Le site ne collecte pas | `SiteNotCollecting` | oui | non | non |
| 2 | `DNT: 1` ou `Sec-GPC: 1` | `OptedOut` | non | non | non |
| 3 | L'agent utilisateur se déclare robot d'indexation | `DeclaredCrawler` | non | non | non |
| 4 | Le référent est sur la liste de pourriel | `ReferrerSpam` | non | non | non |
| 5 | Le verdict est `Robot` | `RobotVerdictOnly` | non | non | oui |
| 6 | Le verdict est `Unknown` | `NoVerdict` | non | non | non |
| 7 | Autrement (verdict `Human`) | `CountAudience` | non | oui | non |

`Admission` est une énumération à valeurs de chaîne (`site_not_collecting`, `opted_out`, `declared_crawler`,
`referrer_spam`, `robot_verdict_only`, `no_verdict`, `count_audience`). `isRefusal()` distingue une requête refusée
d'emblée d'une requête acceptée et non comptée.

Décisions de conception :

- **Un signal de confidentialité ne compte rien, nulle part**, pas même dans le compteur des verdicts robot : il passe
  avant le verdict.
- **Un verdict robot ne fait avancer qu'un compteur agrégé des verdicts robot, jamais l'audience.** `Collector` le tient
  par site et par jour UTC dans le stockage des compteurs, sous la métrique `robot_verdicts`
  (`MeasureNames::ROBOT_VERDICTS`).
- **Un verdict absent ou non reconnu n'est pas un robot.** Le verdict est lu strictement par `Verdict::fromSubmitted()`
  (`' HUMAN '` est `Unknown`). Un verdict inconnu ne compte rien du tout : un détecteur qui échoue dans le navigateur ne
  doit pas changer chaque personne en robot, ce qui cacherait aussi le silence du détecteur derrière une vague de
  robots. Un détecteur silencieux se voit à un compteur des verdicts robot à zéro.
- **L'état est vérifié de nouveau en premier**, même si l'étape 3 de la collecte l'a déjà vérifié, pour qu'aucun
  changement ultérieur du point de collecte ne puisse changer un refus en écriture.

## Le point de collecte

`Infrastructure\Http\CollectController`, route `phil_analytics_collect`, reçoit une page vue ou un événement par `POST`
et le passe à `Application\Collection\Collector`, qui suit l'ordre ci-dessus. Importez la route sous le préfixe de
votre choix ([configuration.md](configuration.md#le-point-de-collecte)) :

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

La route ne répond qu'à `POST`, est sans état (aucune session n'est ouverte), et répond sans corps, avec
`Cache-Control: no-store`. Ce qu'est devenue la requête est un `CollectionOutcome` :

| Issue | Statut | Quand |
|---|---|---|
| `Counted` | 204 | Admise : les compteurs d'audience, ou le compteur des verdicts robot, ont avancé |
| `NotCounted` | 204 | Acceptée et comptée nulle part : un signal de confidentialité, un robot d'indexation déclaré, un référent de pourriel, aucun verdict |
| `Refused` | 403 | Aucun site ne déclare l'origine, le site ne collecte pas, ou l'adresse du client n'est pas une adresse |
| `OverBudget` | 429 | Au-delà du budget du site pour ce client, ou le limiteur n'a pas pu répondre |
| `TooLarge` | 413 | Un corps de plus de `Collector::MAX_BODY_BYTES` (4096 octets) |

Une requête `NotCounted` reçoit la même réponse qu'une requête comptée, pour qu'un script ne puisse pas savoir si un
visiteur a été compté.

**Le corps vient du client et peut contenir n'importe quoi.** Un corps annoncé plus long que 4096 octets est refusé
avant d'être lu, et un corps envoyé sans longueur est lu jusqu'à un octet après la limite, jamais plus loin. Il est
décodé jusqu'à une profondeur de trois (un objet de champs, dont l'un, `dimensions`, est un objet de chaînes), et le
type de chaque champ est vérifié avant usage : les déchets ne comptent rien au lieu de lever une erreur.

Ce qu'écrit une requête admise :

| Requête | Compteurs, pour le site et le jour UTC |
|---|---|
| Une page vue | `pageviews` par `route`, `device` et `country` ; `visits` par `source` quand le référent commence une visite ; puis ce qu'ajoute `MeasureCounters::ofPageView()` — la paire de routes, les dimensions déclarées, les objectifs qu'atteint la route ([measures.md](measures.md)) |
| Un événement (un corps qui a un champ `event`) | Ce que retourne `MeasureCounters::ofEvent()`, et aucune page vue |
| Un verdict robot | `robot_verdicts` par `verdict`, valeur `robot`, et rien dans l'audience |

`Collector` peut aussi être appelé directement, avec une `CollectionRequest` qui contient les en-têtes `Origin`,
`Referer`, `DNT` et `Sec-GPC`, l'adresse du client, l'agent utilisateur et le corps : pour un point de collecte propre à
l'application, derrière un autre cadriciel ou une file.

## Ce que doit aussi garantir le processus qui sert la collecte

Ce sont des règles d'exploitation que le bundle ne peut pas imposer, et que sa conception suppose :

- L'adresse du client n'atteint jamais un journal : aucun journal d'accès sur l'origine de collecte, aucune adresse dans
  les journaux de l'application ni dans les outils de suivi des erreurs.
- Le processus de collecte détient les identifiants du stockage des sels et les siens, rien d'autre.
- Son rôle de base de données peut incrémenter les compteurs et lire la projection de collecte, et rien d'autre.
- Le script de collecte est servi depuis l'origine de l'application elle-même, sans témoin ni stockage local, et
  n'envoie que ce que listent les tables des pages vues et des événements du script : jamais la requête ni le fragment
  d'une page.
