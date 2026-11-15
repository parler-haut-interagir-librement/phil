# The platform

A platform launches tools for its members. It registers each tool — its client, its addresses, its key set, the scopes
it may ask for — and publishes its own key set and endpoints for the tool.

## Registering a tool

```php
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Service\Scope;
use Phil\Url\Url;

$tools = new InMemoryToolRegistrations()->add(new ToolRegistration(
    clientId: 'client-42',
    loginUrl: Url::fromString('https://tool.example.com/lti/login'),
    redirectUrls: [Url::fromString('https://tool.example.com/lti/launch')],
    keySetUrl: Url::fromString('https://tool.example.com/lti/jwks'),
    scopes: [Scope::SCORE, Scope::LINE_ITEM, Scope::MEMBERSHIP_READ_ONLY],
    deepLinkingUrl: Url::fromString('https://tool.example.com/lti/launch'),
    name: 'The quiz tool',
), deploymentIds: ['1']);
```

The client identifier is the platform's to choose: random, never reused. The redirect addresses are compared whole, as
OpenID Connect requires.

## Starting a launch

```php
use Phil\Lti\Platform\LaunchStarter;

$url = new LaunchStarter('https://courses.example.com', $tools)
    ->loginUrl($tool, deploymentId: '1', loginHint: $hintOfTheMember, targetLinkUri: $target, messageHint: $resourceLinkId);
```

The browser goes to that address — in a frame or a window — with the issuer, the login hint, the target, the client,
the deployment and the message hint. The login hint is what the platform recognises the member by when the browser comes
back: an opaque identifier of the account, never their email. The message hint is what it recognises the launch by —
a resource link, a deep linking placement —; the tool echoes it without reading it. The target must be on an origin of
the tool.

## Answering the authentication request

The tool sends the browser back to the platform's authorization endpoint, by GET or POST. The platform reads the
request, checks the tool and its redirect address, builds the message for the member signed in, and signs it.

```php
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Platform\LaunchIssuer;

$issuer = new LaunchIssuer('https://courses.example.com', $tools, $keys, $clock);
$request = AuthenticationRequest::fromParameters($parameters);
[$tool, $redirect] = $issuer->check($request);         // before building anything

$post = $issuer->authorize($request, $hintOfTheMemberSignedIn, $message);
// render a form that posts $post->fields to $post->action, and submits itself
```

`AuthenticationRequest::fromParameters()` refuses a request without `scope=openid`, `response_type=id_token`,
`response_mode=form_post` and `prompt=none`, or without client, redirect address, login hint or nonce. `authorize()`
refuses an unregistered tool or deployment, a redirect address the tool did not register, a login hint that is not the
member's, and an anonymous message that names the person. The `id_token` carries `iss` the platform, `aud` and `azp`
the client, `iat`, `exp` five minutes later, the request's `nonce`, and the claims of the message; the form carries it
with the request's `state`.

## Building the message

`LaunchMessage` holds the claims of LTI; `toClaims()` writes them.

```php
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;

$message = new LaunchMessage(
    MessageType::ResourceLink,
    deploymentId: '1',
    targetLinkUri: $target,
    person: new Person(subject: $opaqueIdOfTheMember, name: 'Ada Lovelace'),
    roles: Roles::of([ContextRole::Learner]),
    resourceLink: new ResourceLink('link-1', 'Week 1'),
    context: new Context('course-1', 'CS101', 'Introduction to computing'),
    custom: ['week' => '1'],
    ags: $agsEndpoint,
    nrps: $nrpsEndpoint,
);
```

The subject is stable for the member and this tool, and opaque: the platform never sends its own primary key or the
member's email as subject. Share the name and the email only when the tool needs them.

## The key set

The platform publishes `KeyStore::publicKeys()->toJson()` at the key set address it gives its tools, with the media type
`application/jwk-set+json`. See [keys](keys.md).

## The token endpoint and the services

Tools ask the token endpoint for access tokens, then call the services: see [AGS](ags.md) and [NRPS](nrps.md).
