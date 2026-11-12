<!-- phil-dev source-sha256=500d541eb1506dca61c5adcde21d7d27b3ec3cc64505abad1e60ed8ff1dd7011 -->
# Mesures

Au-delà des pages vues, un site peut compter ce que font ses visiteurs. **Tout ici reste en agrégat :** chaque envoi
fait avancer des compteurs qui se suffisent à eux-mêmes, sans témoin, sans identifiant de visite et sans fil d'un
compteur à l'autre.

État : chaque type ci-dessous est implémenté et testé. `MeasureCounters` transforme un envoi en compteurs, pour le
point de collecte du bundle ([collection-and-admission.md](collection-and-admission.md#le-point-de-collecte)).

## Le plan d'un site

Un site déclare ce qu'il mesure dans son `MeasurePlan`, le dernier argument de `CollectionSite::of()`. La projection
`config` le construit à partir des `goals`, `forms`, `click_names`, `clicks`, `downloads`, `dimensions` et
`server_events` de chaque site sous `phil_analytics.sites` ; la projection d'une application le construit elle-même :

```php
use Phil\AnalyticsBundle\Domain\Measure\{ClickTracking, ClosedList, DeclaredDimension, Goal, MeasureEvent, MeasurePlan};

$plan = MeasurePlan::of(
    clicks: ClickTracking::Aggregate,              // the default; ClickTracking::Off stops every click measure
    clickNames: ClosedList::of(['register-cta']),
    forms: ClosedList::of(['contact']),
    downloads: ClosedList::downloads(),            // the default list of file extensions
    goals: [
        Goal::onRoute('signup', 'welcome'),
        Goal::onEvent('asked', MeasureEvent::FormSubmit, 'contact'),
    ],
    dimensions: [DeclaredDimension::of('checkout_button', ClosedList::of(['a', 'b']))],
    serverEvents: ClosedList::of(['signup', 'ticket_sold']),
);
```

**Une valeur qui n'est pas listée ne compte rien.** Une requête forgée ne crée aucun compteur, et aucune liste ne peut
contenir un nom. Les listes sont bornées : 256 valeurs chacune, 64 objectifs, 16 dimensions. Un événement du serveur
commence par une lettre et compte au plus 50 caractères.

## Ce qu'envoie le script

Le contrôleur du [script de collecte](collection-and-admission.md#le-script-de-collecte) prend ces valeurs :

| Valeur | Par défaut | Sens |
|---|---|---|
| `clicks` | `aggregate` | `off` n'ajoute aucun écouteur de clic |
| `downloads` | `[]` | Les extensions comptées, d'après `$plan->downloads->toArray()` |
| `dimensions` | `{}` | La valeur de chaque dimension déclarée sur cette page |
| `status` | `200` | `404` signale une page introuvable |
| `search-term` | vide | Le terme d'une page de résultats de recherche |
| `search-results` | `-1` | Le nombre de résultats ; négatif quand il est inconnu |

Un événement reprend le `path`, le `referrer`, le `verdict` et les `dimensions` de la page, avec son nom dans `event` :

| `event` | Champs | Quand | Compteur (métrique, dimension, valeur) |
|---|---|---|---|
| `click` | `name` | Un élément porteur de `data-phil-analytics-click` est cliqué | `clicks`, `name`, le nom déclaré |
| `download` | `extension` | Un lien vers une extension listée est cliqué | `events`, `download`, l'extension |
| `outbound` | `host`, `link` | Un lien vers un autre hôte est cliqué | `outbound`, `host`, l'hôte ; `outbound_link`, `link`, la référence |
| `form_submit` | `form` | Un formulaire porteur de `data-phil-analytics-form` est soumis | `events`, `form`, le nom déclaré |
| `not_found` | aucun | Le `status` de la page est 404 | `events`, `not_found`, la classe de sa source |
| `search` | `term`, `results` | La page a un `search-term` | `search`, `term`, le terme ; `search`, `outcome`, `found` ou `none` |
| `engagement` | `scroll`, `active` | On quitte la page | `engagement`, `scroll`, une tranche ; `engagement`, `active`, une tranche |

Chaque événement passe par la même admission qu'une page vue : rien n'est envoyé, ni compté, sous Do Not Track ou
Global Privacy Control. Le chemin d'une page introuvable n'est pas gardé : c'est un texte ouvert, et seule la classe de
la source l'est.

## Clics

`ClickTracking::Aggregate` est la valeur par défaut : un compteur par nom déclaré, lié à personne.
`ClickTracking::Off` arrête les clics nommés, les téléchargements et les liens sortants, dans le script et au serveur.
Le bundle n'a aucun niveau par personne, puisqu'il ne garde aucun identifiant auquel lier un clic.

## Objectifs et conversion

Un objectif est atteint par une route déclarée ou par un événement d'une valeur déclarée ; chaque fois ajoute un à
`goals`, `goal`, son nom. Un taux de conversion divise ce compteur par les visites du site, lues sur les mêmes jours :

```php
$reached = $store->counts($site, MeasureNames::GOALS, 'goal', $from, $to)['signup'] ?? 0;
$visits = array_sum($store->counts($site, MeasureNames::VISITS, 'source', $from, $to));

$rate = ConversionRate::of($reached, $visits, $threshold);  // null when either is under the threshold
$rate?->basisPoints;                                        // 1250 for 12.5 %
```

Aucune visite n'est suivie : une visite qui atteint un objectif deux fois compte deux fois, et un taux peut dépasser un.

## Liens sortants

**Tout hôte est compté**, sous `outbound`, `host` ; les hôtes sont une dimension ouverte, lisez-les donc par
`SmallNumberSuppression` comme tout autre compteur. Seul l'hôte quitte la page : le chemin et la requête d'un lien
peuvent contenir un jeton.

**Le compte de chaque lien** vaut pour les liens qu'une application a écrits elle-même dans une page. La page donne à un
tel lien une référence opaque, et `OutboundLinkDirectory` dit à qui il est :

```twig
<a href="{{ link.url }}" data-phil-analytics-link="{{ link.reference }}">{{ link.label }}</a>
```

Une référence que l'annuaire ne connaît pas compte l'hôte seulement. Ces compteurs sont exacts : **seuls l'auteur du
lien et les administrateurs les lisent**, par `LinkCountReader` :

```php
$counts = $store->counts($site, MeasureNames::OUTBOUND_LINK, 'link', $from, $to);

LinkCountReader::author($account->reference)->counts($links, $counts);   // the author's links only
LinkCountReader::administrator()->counts($links, $counts);               // every link
```

Aucune vue partagée ne les contient ([sharing-reports-alerts.md](sharing-reports-alerts.md)).

## Paires de routes

Une page vue venue d'une autre page du même site ajoute un à `transitions`, `route_pair`, `home>event`. Les deux côtés
doivent être des routes déclarées. Un compteur par paire, et aucun fil entre les paires : aucun parcours individuel ne
peut être reconstitué.

## Profondeur de lecture et temps actif

Quand on quitte une page, ou que Turbo la remplace, le script envoie une fois de plus, une seule, jusqu'où la page a
été lue (`0`, `25`, `50`, `75`, `100`) et pendant combien de secondes elle a été visible. Le serveur ne garde que des
tranches (`ScrollDepth`, `ActiveTime`). Cet envoi se fait sur `pagehide`, et ne demande ni témoin ni stockage : la
mesure vit dans le contrôleur, le temps de la page. Les tranches sont comptées pour le site dans son ensemble, puisque
les croiser avec les routes rapetisserait chaque compteur vers la taille où il désigne une personne.

## Termes de recherche

`SearchTerm::valueOf()` met un terme en minuscules et réduit ses espaces. Un terme de 1 caractère ou de plus de 64, ou
qui contient un `@`, un `://` ou une suite de six chiffres, n'est pas compté : il peut nommer quelqu'un. Les termes sont
une dimension ouverte : lisez-les par `SmallNumberSuppression`, qui ne montre que les fréquents. Le cumul mensuel oublie
les rares ([retention.md](retention.md)), et les vues partagées ne les montrent jamais.

## Variantes

Une variante d'une expérience, ou toute autre étiquette, est une **dimension déclarée** : le bundle ne lit pas les
drapeaux de fonctionnalité. La page passe au script la valeur qu'elle a déjà calculée :

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-dimensions-value="{{ {checkout_button: variant}|json_encode|e('html_attr') }}">
```

Une page vue ajoute à `pageviews` et, pour une visite, à `visits`, sous la dimension. Un objectif atteint ajoute à
`goals.<name>` sous la dimension, pour que les variantes convertissent côte à côte :

```php
$conversions = $store->counts($site, 'goals.signup', 'checkout_button', $from, $to);
$visits = $store->counts($site, MeasureNames::VISITS, 'checkout_button', $from, $to);
```

Une dimension ne peut pas s'appeler `route`, `device`, `country` ni `source`.

## Événements comptés par le serveur

Certains événements n'atteignent jamais une page : un compte créé, un billet vendu, une notification livrée.
`ServerEventCounter` les compte depuis le code de l'application, pour tout site que tient la projection, sous les mêmes
règles que le point de collecte :

```php
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;

$events->count(SiteId::fromString('spring-con'), 'signup', ['plan' => 'pro']);   // true: counted
$events->count(SiteId::fromString('spring-con'), 'login');                       // false: not declared
```

| Compteur (métrique, dimension, valeur) | Avancé par |
|---|---|
| `server_events`, `event`, l'événement | Chaque événement compté |
| `server_events.<event>`, la dimension, la valeur | Chaque dimension déclarée envoyée avec l'une de ses valeurs déclarées |

L'événement doit figurer dans les `server_events` du site ; une dimension non déclarée, ou une valeur hors de sa liste,
est écartée, et un site suspendu ou retiré ne compte rien. **Rien de ce qui nomme une personne ne devient un
compteur** : passez le forfait d'un compte, jamais sa référence. `count()` dit si l'événement a été compté, pour qu'une
faute dans le nom d'un événement se voie dans les tests de l'application. Le jour est le jour UTC de l'horloge, comme
pour une page vue.

Un seul `ServerEventCounter` sert tous les sites : une application qui fait tourner plusieurs sites sur une même
plateforme compte les événements de chaque site d'après la déclaration de ce site.
