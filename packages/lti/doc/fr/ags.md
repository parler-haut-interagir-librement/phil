<!-- phil-dev source-sha256=ba5923a4c6cd57b482f7f045d96e50f76445559a529b54e87ba1a2211f858673 -->
# Assignment and Grade Services

Les Assignment and Grade Services permettent à l'outil de tenir des colonnes — les line items — dans le carnet de notes
de la plateforme, de leur envoyer des notes et de lire les résultats. La plateforme les accorde par outil, par portée,
et les nomme dans le lancement.

## Portées

| Constante de `Scope` | Ce qu'elle permet |
|---|---|
| `LINE_ITEM` | Lire, créer, modifier et supprimer les line items du contexte. |
| `LINE_ITEM_READ_ONLY` | Les lire. |
| `RESULT_READ_ONLY` | Lire les résultats d'un line item. |
| `SCORE` | Envoyer des notes. |

## Jetons d'accès

Chaque appel porte un jeton porteur du point de terminaison des jetons de la plateforme. `AccessTokenProvider` le
demande avec le type d'autorisation `client_credentials`, l'outil s'authentifiant par un JWT signé avec sa clé
(RFC 7523) : `iss` et `sub` le client, `aud` le point de terminaison des jetons — ou le `tokenAudience` de
l'enregistrement —, cinq minutes de vie, un `jti` neuf. Le jeton est gardé dans le cache par plateforme et par
ensemble de portées, jusqu'à 30 secondes avant son expiration.

## Du côté de l'outil

```php
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\ServiceCall;

$client = new SafeHttpServiceClient();
$ags = new AgsClient(new ServiceCall($client, new AccessTokenProvider($client, $keys, $cache, $clock)));

$ags->publishScore($platform, $launch->message->ags, new Score(
    userId: $launch->subject(),
    activityProgress: ActivityProgress::Completed,
    gradingProgress: GradingProgress::FullyGraded,
    timestamp: $clock->now(),
    scoreGiven: 8.0,
    scoreMaximum: 10.0,
));
```

| Méthode | Portée | Ce qu'elle fait |
|---|---|---|
| `lineItems()` | `LINE_ITEM` ou `LINE_ITEM_READ_ONLY` | Une page de line items, filtrés par lien de ressource, ressource ou étiquette ; `next` est l'adresse de la page suivante. |
| `lineItem()` | la même | Un line item, par son adresse. |
| `createLineItem()` | `LINE_ITEM` | Crée un line item ; la plateforme donne son adresse. |
| `updateLineItem()` | `LINE_ITEM` | Modifie un line item, par son adresse. |
| `deleteLineItem()` | `LINE_ITEM` | Supprime un line item. |
| `publishScore()` | `SCORE` | Envoie une note au line item du lancement, ou à celui qui est donné, à `{lineitem}/scores`. |
| `results()` | `RESULT_READ_ONLY` | Les résultats d'un line item, ceux d'un apprenant lorsqu'il est donné. |

Une méthode refuse, avant toute requête, un lancement dont le point de terminaison n'accorde pas sa portée. Une note
garde son horodatage : la plateforme garde la plus récente par apprenant. `scoreGiven` vient avec son `scoreMaximum` ;
une note sans eux dit où en est l'apprenant.

## Du côté de la plateforme

```php
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Platform\Token\TokenEndpoint;

$issued = new TokenEndpoint($tokenUrl, $tools, $accessTokens, $nonces, $verifier, $clock)->issue($_POST);
// answer $issued->toArray() as JSON, never cached

$token = new ServiceGuard($accessTokens)->authorize($authorizationHeader, [Scope::SCORE]);
$kept = new AgsService($roster, $lineItems, $scores)->score($token, $contextId, $lineItemId, Score::fromArray($body));
```

`TokenEndpoint::issue()` vérifie le type d'autorisation et l'assertion — émise par un client enregistré pour
lui-même, adressée au point de terminaison des jetons, signée par une clé du jeu de clés de l'outil, ses heures
valides, son `jti` employé une seule fois — et accorde les portées demandées qui sont connues et enregistrées pour
l'outil. Le jeton est fait de 32 octets aléatoires, donné une fois et gardé par son SHA-256, pendant une heure par
défaut. Un refus est une `TokenRequestRefused` portant le code d'erreur de la RFC 6749.

`AgsService` vérifie la portée de chaque opération, que l'outil est placé dans le contexte — par le `Roster` —, et que
le line item est celui de l'outil. Une méthode qui ne trouve aucun contexte ou line item de l'outil répond null ou
false, de sorte que l'application répond 404. Une note plus ancienne que la dernière de l'apprenant sur le line item
n'est pas gardée ; les résultats ramènent la dernière note à l'échelle du maximum du line item. L'application donne les
adresses des line items et des résultats.

`ScoreStore::record()` compare et écrit en une seule étape atomique et dit s'il a gardé la note : de deux notes d'un même
apprenant écrites en même temps, la plus récente reste, quel que soit l'ordre d'arrivée, et `score()` répond false pour
l'autre, que l'application répond par 409. Une note du même moment que celle qui est gardée la remplace, pour qu'un
outil qui envoie de nouveau une note ne soit pas refusé.

`lineItems()` répond une page, comme `NrpsService::page()` :

```php
$page = $ags->lineItems($token, $contextId, limit: $limit, offset: $offset);
// ['lineItems' => list<StoredLineItem>, 'limit' => 100, 'nextOffset' => 100]
```

La page contient `limit` line items au plus — 100 quand l'outil n'en demande aucun, 1 000 au plus quoi qu'il demande —,
et `limit` est cette taille, celle à laquelle la page a été coupée. On demande au magasin un line item de plus que la
page, pour que `nextOffset` soit le début de la page suivante, ou null sur la dernière, à toute taille : l'application
écrit le lien `next` à partir de `limit` et de `nextOffset`, aussi quand l'outil n'a demandé aucune limite, ou plus de
1 000.
