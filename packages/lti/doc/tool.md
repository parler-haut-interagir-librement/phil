# The tool

A tool is launched by platforms. Each platform registers it once — giving it a client identifier, deployments, and its
own endpoints — and the tool registers the platform in return.

## Registering a platform

```php
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Url\Url;

$platforms = new InMemoryPlatformRegistrations()->add(new PlatformRegistration(
    issuer: 'https://lms.example.org',                                   // the `iss` of its tokens
    clientId: 'client-id-given-by-the-platform',                         // the `aud` of its tokens
    authorizationUrl: Url::fromString('https://lms.example.org/lti/authorize'),
    tokenUrl: Url::fromString('https://lms.example.org/lti/token'),
    keySetUrl: Url::fromString('https://lms.example.org/lti/jwks'),
    tokenAudience: null,                                                 // the token endpoint's address when null
), deploymentIds: ['deployment-id']);
```

A platform may register the tool several times under one issuer: `find()` then needs the client, which most platforms
send in the login initiation. A launch from a deployment the tool does not know is refused.

The tool gives the platform, in return, its login initiation address, its launch address — the redirect address of
OpenID Connect —, its key set address, and, for Deep Linking, the address of its deep linking launch.

## The login initiation

The platform sends the browser to the tool's login initiation, by GET or POST, with `iss`, `login_hint`,
`target_link_uri`, and often `lti_message_hint`, `client_id` and `lti_deployment_id`.

```php
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;

$initiator = new LoginInitiator($platforms, $states, $clock, stateLifetime: 600);
$redirect = $initiator->initiate(LoginRequest::fromParameters($parameters), $launchUrl);
// redirect the browser to $redirect->url
```

`initiate()` checks that the platform and its deployment are registered and that the target of the launch is on the
origin of the launch address — scheme, host and port —, so that a forged initiation cannot send the person elsewhere
after the launch. It makes a `state` and a `nonce` of 32 random bytes each, keeps them in `LoginStates` for ten minutes
by default, and answers the address of the platform's authorization endpoint with the authentication request:
`scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none`, the client, the redirect address,
the login hint, the message hint, the `state` and the `nonce`.

## The launch

The platform posts `id_token` and `state` to the launch address.

```php
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Tool\LaunchValidator;

$validator = new LaunchValidator($platforms, $states, $nonces, new JwtVerifier($keySets, $clock));
$launch = $validator->validate($idToken, $state);
```

`validate()` takes the state from its store — once: a second launch with the same state is refused — and then checks,
in order:

1. the token is issued by the platform of the state and addressed to its client: `iss` equal, `aud` the client or a list
   that holds it, `azp` the client when present, required when `aud` lists several;
2. its signature verifies with `RS256` and the key its `kid` names in the platform's key set;
3. `exp` is in the future and `iat` neither in the future nor older than ten minutes, five seconds of leeway each way;
4. its `nonce` is the state's, and was never used before;
5. the claims of LTI are well formed, the deployment registered, and the target the one of the login.

Any failed check throws: `InvalidRequest` for the state, `InvalidToken` for the token, `UnknownRegistration` for the
platform or the deployment. Each says which check failed and never repeats the token.

## What a launch carries

`Launch` gives the platform's issuer and client, the typed `LaunchMessage`, and every claim of the token in `claims`.

| Member | What it is |
|---|---|
| `type()` | `MessageType::ResourceLink` or `MessageType::DeepLinking`. |
| `deploymentId()` | The deployment of the tool on the platform. |
| `subject()` | The `sub`: the person, stable for this platform and client; null in an anonymous launch. |
| `userKey()` | The SHA-256 of the issuer, the client and the subject: an identifier of the person unique across platforms. |
| `roles()` | `Roles`: `has()`, `isInstructor()`, `isLearner()`, `isTestUser()`. A context role sent by its short name is read as its full URI. |
| `message->person` | The name, given and family names, email, picture and locale, as far as the platform shares them. |
| `message->context` | The course, section or group: identifier, label, title, types. |
| `message->resourceLink` | The placement of the tool: identifier, title, description. |
| `message->presentation` | Where the tool is shown, its size, the locale, the return address. |
| `message->platform` | The platform instance as it describes itself. |
| `message->custom` | The custom parameters, after the platform replaced its variables, as text. |
| `message->ags` | The endpoint of the Assignment and Grade Services, when the platform grants it: see [AGS](ags.md). |
| `message->nrps` | The endpoint of the Names and Role Provisioning Service: see [NRPS](nrps.md). |
| `message->deepLinking` | The settings of a deep linking request: see [Deep Linking](deep-linking.md). |

A claim the package does not type — of another specification, or of the platform — is read from `claims`.

## Addresses on localhost

Every address goes through `phil/url`, which refuses `localhost` and IP addresses. In development, pass
`allowLocalhost: true` to `LoginInitiator`, `LaunchValidator`, the clients of the services and `LaunchMessage::fromClaims()`;
never in production.
