# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## What the application implements

| Port | When | What it does |
|---|---|---|
| `Phil\LtiBundle\Application\Contract\LaunchMessages` | The platform side is enabled | The login hint of a member, and the message of a launch for the member signed in. |
| `Phil\Lti\Contract\Roster` | The platform answers its services | The contexts, the tools placed in each, and their members; an empty roster until then. |
| `Phil\LtiBundle\Application\Contract\LaunchUsers` | Optional, on the tool | The application's account of a launch; an `LtiUser` without it. |
| `Phil\LtiBundle\Application\Contract\KeySealer` | Optional | Seals the private keys; `KeyManagementSealer` by default. |

## The stores

Each port of `phil/lti` the bundle uses is aliased to its adapter; an application aliases the port to its own service
to keep part of the state elsewhere:

| Port | `dbal` | `memory` |
|---|---|---|
| `Phil\Lti\Contract\Nonces` | `DbalNonces` | `InMemoryNonces` |
| `Phil\Lti\Contract\LoginStates` | `DbalLoginStates` | `InMemoryLoginStates` |
| `Phil\Lti\Contract\AccessTokens` | `DbalAccessTokens` | `InMemoryAccessTokens` |
| `Phil\Lti\Contract\LineItemStore` | `DbalLineItemStore` | `InMemoryLineItemStore` |
| `Phil\Lti\Contract\ScoreStore` | `DbalScoreStore` | `InMemoryScoreStore` |
| `Phil\Lti\Contract\KeyStore` | `DbalKeyStore` (keys `dbal`) | `InMemoryKeyStore` |
| `Phil\LtiBundle\Application\Contract\PendingScores` | `DbalPendingScores` | `InMemoryPendingScores` |
| `Phil\LtiBundle\Application\Contract\DeepLinkingReturns` | `DbalDeepLinkingReturns` | `InMemoryDeepLinkingReturns` |
| `Phil\Lti\Contract\PlatformRegistrations` | from `tool.platforms` | the same |
| `Phil\Lti\Contract\ToolRegistrations` | from `platform.tools` | the same |

An adapter keeps the contract of its port:

- `ScoreStore::record()` compares and writes in one atomic step, and answers whether it kept the score (see the
  extension points of `phil/lti`);
- `PendingScores::claim()` takes each score in one atomic step — a guarded update, or a lock — and marks it claimed at
  the moment given, so that of two recoveries at once each score goes to one; `markDispatched()` and `markRefused()` do
  nothing for a score that is gone; a refused or dispatched score is never claimed;
- `DeepLinkingReturns::save()` keeps the first response saved under a key; `find()` and `unhandled()` never answer a
  response expired.

`PendingScores` has the shape of an outbox — `save()` writes the entry, `markDispatched()` acknowledges it, `claim()`
polls, `refused()` and `delete()` keep and purge the dead letters —: an application with an outbox of its own implements
the port over it.

## The events

| Event | Side | What a listener does |
|---|---|---|
| `LaunchReceived` | Tool | Answers a verified launch with `setResponse()`. |
| `ScorePublished` | Tool | Learns that a score was taken by the platform. |
| `ScoreRefused` | Tool | Keeps its own trace of a score the platform refused for good. |
| `DeepLinkingReturned` | Platform | Places the items picked, once per `key`, and answers with `setResponse()`; idempotent, as a response whose handling failed is told again with `resumed` true. |
| `LineItemSaved`, `LineItemDeleted` | Platform | Keeps the gradebook's columns. |
| `ScoreReceived` | Platform | Keeps the gradebook's grades. |

## The services to use

`ScorePublisher`, `DeepLinkingResponder`, `DeepLinkingHandler`, `FormPostResponder`, `AgsClient`, `NrpsClient`,
`LaunchStarter`, `PlatformUrls` and `KeyStore` are public services.

## What stays closed

The controllers, the authenticator and the checks of `phil/lti` are final. The templates may be overridden as any
bundle's, in `templates/bundles/PhilLtiBundle/`.
