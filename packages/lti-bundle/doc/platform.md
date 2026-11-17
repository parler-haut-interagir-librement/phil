# The platform

## Starting a launch

`LaunchStarter` gives the address of a tool's login initiation; the application opens it in a frame or a window:

```php
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Platform\LaunchStarter;

$tool = $tools->find('client-42');
$url = $starter->loginUrl($tool, '1', $launchMessages->loginHint($member), $targetLinkUri, messageHint: $resourceLinkId);
```

## The application's launches

The platform side needs the application's `LaunchMessages`: what the platform recognises a member by, and the message
of a launch, found by its message hint, for the member signed in.

```php
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Registration\ToolRegistration;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;

final readonly class CourseLaunches implements LaunchMessages
{
    public function __construct(private PlatformUrls $urls, private Placements $placements) {}

    public function loginHint(UserInterface $user): string
    {
        return $user->getUserIdentifier();
    }

    public function message(AuthenticationRequest $request, ToolRegistration $tool, UserInterface $user): LaunchMessage
    {
        $placement = $this->placements->forMember((string) $request->messageHint, $user);

        return new LaunchMessage(
            MessageType::ResourceLink,
            $placement->deploymentId,
            $placement->target,
            new Person($placement->subjectFor($user)),
            Roles::of([$placement->teaches($user) ? ContextRole::Instructor : ContextRole::Learner]),
            new ResourceLink($placement->id, $placement->title),
            new Context($placement->courseId, $placement->courseCode, $placement->courseTitle),
            ags: $this->urls->agsEndpoint($tool, $placement->courseId, $placement->lineItemId),
            nrps: $this->urls->nrpsEndpoint($placement->courseId),
        );
    }
}
```

`PlatformUrls` gives the addresses of the services for the claims, with the scopes the tool is registered for.

## The authorization endpoint

`phil_lti_platform_authorize` (`/lti/platform/authorize`), behind the firewall of the members, reads the authentication
request by GET or POST. A request whose client or redirect address is not registered answers 400. Once the redirect
address is known, `LaunchMessages` builds the message and `LaunchIssuer` signs it; the page posts the `id_token` and the
`state` to the tool and submits itself. A refusal of the application — `InvalidRequest` from `message()`, a login hint
that is not the member's — is posted back to the tool as OpenID Connect writes errors: `error`, `error_description` and
the `state`. A request without a member signed in answers 401.

## Deep Linking

The application launches a deep linking request with a message of type `MessageType::DeepLinking` and its
`DeepLinkingSettings`, the return address being `phil_lti_platform_deep_linking` and `data` naming the placement. The
tool posts its response there; the bundle verifies it with `DeepLinkingReturn`, keeps it, and dispatches
`DeepLinkingReturned`. A listener places the items and answers the browser; without an answer, the bundle shows a short
page with the count of the items and the tool's message.

### Resuming a response

The `nonce` of a response is taken once: the token the browser posted is never accepted again. So that a listener that
fails does not lose the response, `DeepLinkingHandler` keeps it before it tells the application — its key, its client and
the claims it was verified with, in the table `deep_linking` or in memory, for `platform.deep_linking_retention` seconds,
a day by default — and marks it handled once every listener returned. The claims are those the tool picked for the
platform — content items, the `data` the platform put in its request, messages —: no credential and no claim about a
person, so they are kept as they are, 64 KiB of JSON at most.

`DeepLinkingReturned::$key` names the response for good: a hash of its client and of its nonce, the same when the
response is told again. A listener is idempotent by this key — it places the items of a key once, and finds what it
placed the first time when it sees the key again. When a listener throws, the browser gets the error and the response
stays kept, not handled. The application tells it again, from the claims kept and without the token:

```php
use Phil\LtiBundle\Application\DeepLinkingHandler;

$event = $handler->resume($key);                 // null when nothing is kept under the key, or it expired
foreach ($handler->unhandled(300) as $key) { … }  // the responses not handled after five minutes
```

```bash
bin/console phil:lti:resume-deep-linking <key>       # one response, handled or not
bin/console phil:lti:resume-deep-linking --delay=300 # every response not handled after five minutes
```

`DeepLinkingReturned::$resumed` is true for a response told again; a listener has no browser to answer then, and its
response is not used. `phil:lti:purge` removes the responses expired.

## The token endpoint

`phil_lti_platform_token` (`/lti/platform/token`) answers `TokenEndpoint::issue()` as JSON, never cached; a refusal with
its OAuth error code, 401 for a client that does not authenticate, 400 otherwise. Its address is the configuration's
`platform.token_url`, the one the tools register.

## The services

| Route | Method | Path under the prefix | Scope |
|---|---|---|---|
| `phil_lti_platform_line_items` | GET, POST | `/contexts/{context}/lineitems` | `lineitem`, `lineitem.readonly` to read |
| `phil_lti_platform_line_item` | GET, PUT, DELETE | `/contexts/{context}/lineitems/{id}` | the same |
| `phil_lti_platform_scores` | POST | `/contexts/{context}/lineitems/{id}/scores` | `score` |
| `phil_lti_platform_results` | GET | `/contexts/{context}/lineitems/{id}/results` | `result.readonly` |
| `phil_lti_platform_memberships` | GET | `/contexts/{context}/memberships` | `contextmembership.readonly` |

Each call carries a bearer token: without one, 401 with `WWW-Authenticate: Bearer error="invalid_token"`; without its
scope, 403 with `error="insufficient_scope"`. A context where the tool is not placed, or a line item that is not the
tool's, answers 404. The lists take `limit` and `offset`, and give the next page in a `Link` header; the line items take
`resource_link_id`, `resource_id` and `tag`, the results `user_id`, the memberships `role`. A body is JSON of 64 KiB at
most.

A page of line items or of members holds 100 entries when the tool asks for no `limit`, and 1,000 at most whatever it
asks. Its `Link: <…>; rel="next"` carries the filters, the `limit` the page was cut at and the `offset` of the next
page, and is there whenever a next page is: also without a `limit`, and with a `limit` beyond 1,000.

A score older than the learner's latest answers 409. The comparison and the write are one atomic step of the store: of
two scores of one learner sent at once, the more recent stays, whatever order they arrive in, and the other answers 409.
A score of the same moment as the one kept replaces it.

## The roster and the gradebook

The services read the application's `Roster` — its contexts, the tools placed in each, their members —; until the
application aliases `Phil\Lti\Contract\Roster` to its own, an empty roster answers 404 for every context. The line items
and the latest scores are kept in the bundle's tables; the gradebook stays the application's, which listens:

| Event | When |
|---|---|
| `LineItemSaved` | A tool created (`created` true) or changed a line item. |
| `LineItemDeleted` | A tool deleted a line item, with its scores. |
| `ScoreReceived` | A score was kept as the learner's latest on a line item. |
| `DeepLinkingReturned` | A deep linking response was verified and kept, or is told again (`resumed` true), under its `key`. |
