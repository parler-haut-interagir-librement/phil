# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## The ports

| Port | What it keeps | Adapter in memory |
|---|---|---|
| `Phil\Lti\Contract\PlatformRegistrations` | The platforms the tool is registered with, and their deployments. | `InMemoryPlatformRegistrations` |
| `Phil\Lti\Contract\ToolRegistrations` | The tools the platform registered, and their deployments. | `InMemoryToolRegistrations` |
| `Phil\Lti\Contract\LoginStates` | The login states, each taken once. | `InMemoryLoginStates` |
| `Phil\Lti\Contract\Nonces` | Values used once, until they expire. | `InMemoryNonces` |
| `Phil\Lti\Contract\KeyStore` | The signing key and the key set of this side. | `InMemoryKeyStore` |
| `Phil\Lti\Contract\KeySetSource` | The key sets of the other side, by address. | `SafeHttpKeySetSource`, `CachedKeySetSource` |
| `Phil\Lti\Contract\ServiceClient` | The requests to the services of the other side. | `SafeHttpServiceClient`, over `phil/safe-http` |
| `Phil\Lti\Contract\AccessTokens` | The access tokens the platform granted, by hash. | `InMemoryAccessTokens` |
| `Phil\Lti\Contract\LineItemStore` | The line items of the platform. | `InMemoryLineItemStore` |
| `Phil\Lti\Contract\ScoreStore` | The latest score of each learner on each line item. | `InMemoryScoreStore` |
| `Phil\Lti\Contract\Roster` | The contexts of the platform, the tools placed in each, and their members. | `InMemoryRoster` |
| `Phil\Lti\Contract\ContentItem` | A content item of Deep Linking. | the five item classes |

An adapter keeps the contract of its port:

- `LoginStates::take()` removes the state in the step that reads it: of two launches with one state, one only finds it;
- `Nonces::use()` checks and writes in one step: of two requests with one value, one only succeeds;
- `AccessTokens::find()` answers null for an expired token;
- `LineItemStore::list()` and `ScoreStore::scores()` answer in a stable order, so that pages do not overlap;
  `LineItemStore::delete()` removes the scores of the line item too.

The adapters in memory forget everything on `reset()`, which a worker calls between requests.

## What stays closed

The checks of a token, of a launch, of an authentication request, of an assertion and of a deep linking response are
not extension points: their classes are final, and a change to them is a change of the package.
