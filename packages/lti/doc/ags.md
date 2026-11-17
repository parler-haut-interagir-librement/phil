# Assignment and Grade Services

The Assignment and Grade Services let the tool keep columns — line items — in the platform's gradebook, send scores to
them, and read the results. The platform grants them per tool, by scope, and names them in the launch.

## Scopes

| `Scope` constant | What it allows |
|---|---|
| `LINE_ITEM` | Read, create, change and delete the line items of the context. |
| `LINE_ITEM_READ_ONLY` | Read them. |
| `RESULT_READ_ONLY` | Read the results of a line item. |
| `SCORE` | Send scores. |

## Access tokens

Each call carries a bearer token of the platform's token endpoint. `AccessTokenProvider` asks for it with the grant
`client_credentials`, the tool authenticated by a JWT signed with its key (RFC 7523): `iss` and `sub` the client, `aud`
the token endpoint — or the registration's `tokenAudience` —, five minutes of life, a fresh `jti`. The token is kept in
the cache per platform and set of scopes, until 30 seconds before it expires.

## On the tool

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

| Method | Scope | What it does |
|---|---|---|
| `lineItems()` | `LINE_ITEM` or `LINE_ITEM_READ_ONLY` | A page of the line items, filtered by resource link, resource or tag; `next` is the address of the following page. |
| `lineItem()` | the same | One line item, by its address. |
| `createLineItem()` | `LINE_ITEM` | Creates a line item; the platform gives its address. |
| `updateLineItem()` | `LINE_ITEM` | Changes a line item, by its address. |
| `deleteLineItem()` | `LINE_ITEM` | Deletes a line item. |
| `publishScore()` | `SCORE` | Sends a score to the launch's line item, or to the one given, at `{lineitem}/scores`. |
| `results()` | `RESULT_READ_ONLY` | The results of a line item, of one learner when given. |

A method refuses, before any request, a launch whose endpoint does not grant its scope. A score keeps its timestamp:
the platform keeps the latest per learner. `scoreGiven` comes with its `scoreMaximum`; a score without them says where
the learner is.

## On the platform

```php
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Platform\Token\TokenEndpoint;

$issued = new TokenEndpoint($tokenUrl, $tools, $accessTokens, $nonces, $verifier, $clock)->issue($_POST);
// answer $issued->toArray() as JSON, never cached

$token = new ServiceGuard($accessTokens)->authorize($authorizationHeader, [Scope::SCORE]);
$kept = new AgsService($roster, $lineItems, $scores)->score($token, $contextId, $lineItemId, Score::fromArray($body));
```

`TokenEndpoint::issue()` checks the grant, the assertion — issued by a registered client for itself, addressed to the
token endpoint, signed by a key of the tool's key set, its times holding, its `jti` used once — and grants the scopes
asked for that are known and registered for the tool. The token is 32 random bytes, given once and kept by its
SHA-256, for an hour by default. A refusal is a `TokenRequestRefused` with the error code of RFC 6749.

`AgsService` checks the scope of each operation, that the tool is placed in the context — by the `Roster` —, and that the
line item is the tool's. A method that finds no context or line item of the tool answers null or false, so that the
application answers 404. A score older than the learner's latest on the line item is not kept; results scale the latest
score to the line item's maximum. The application gives the addresses of the line items and of the results.

`ScoreStore::record()` compares and writes in one atomic step and says whether it kept the score: of two scores of one
learner written at once, the more recent stays, whatever order they arrive in, and `score()` answers false for the
other, which the application answers with 409. A score of the same moment as the one kept replaces it, so that a tool
that sends a score again is not refused.

`lineItems()` answers a page, as `NrpsService::page()` does:

```php
$page = $ags->lineItems($token, $contextId, limit: $limit, offset: $offset);
// ['lineItems' => list<StoredLineItem>, 'limit' => 100, 'nextOffset' => 100]
```

The page holds `limit` line items at most — 100 when the tool asks for none, 1,000 at most whatever it asks —, and
`limit` is that size, the one the page was cut at. The store is asked for one line item more than the page, so that
`nextOffset` is where the next page starts, or null on the last, at every size: the application writes the `next` link
from `limit` and `nextOffset`, also when the tool asked for no limit, or for more than 1,000.
