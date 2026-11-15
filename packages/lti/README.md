# phil/lti

**LTI 1.3 in plain PHP, on both sides: a tool that platforms launch, and a platform that launches tools, with Deep
Linking and the services of LTI Advantage.**

LTI 1.3 is how a learning platform opens an external tool for a person — a quiz, a lab, a reader, a video —, tells it
who they are, in which course and with which roles, and lets it send grades back. phil/lti implements it on both
sides:

- **the tool** answers the platform's login initiation with an OpenID Connect authentication request, then verifies
  the `id_token` the platform posts back: signed with `RS256` by a key of the platform's key set, read through
  `phil/safe-http` and kept in a cache; `iss`, `aud`, `azp`, `exp` and `iat` checked by a PSR-20 clock; the `state` and
  the `nonce` used once; the deployment registered; every claim of LTI typed;
- **the platform** starts a launch, answers the tool's authentication request with a signed `id_token`, publishes its
  key set, issues access tokens, keeps line items and scores, and lists the members of a context;
- **Deep Linking 2.0**: the tool answers an `LtiDeepLinkingRequest` with a signed response of content items, and the
  platform verifies it and reads the items;
- **Assignment and Grade Services**: line items read, created, changed and deleted, scores sent, results read;
- **Names and Role Provisioning Service**: the members of a context, page after page by the `Link` header;
- **access tokens**: the grant `client_credentials` with a JWT assertion signed by the tool's key (RFC 7523), kept in
  a cache until shortly before they expire.

It is for PHP applications that open other tools to their learners, or that are opened by learning platforms: a course
site, a learning management system, a quiz, a simulation, a reading tool.

The library is plain PHP: the tokens are signed and verified through `web-token/jwt-library`, once their form is read
within bounds; the addresses go through `phil/url`; the key sets and every authenticated call to a service — the token
endpoint, the grades, the roster — through `phil/safe-http`, guarded against private networks; the caches through
Symfony's cache contracts, the time through PSR-20. The stores — registrations, deployments, login
states, nonces, keys, access tokens, line items, scores, rosters — are ports with adapters in memory; `phil/lti-bundle`
brings them to Symfony with tables of Doctrine DBAL.

## Status

| Part | State |
|---|---|
| Tool: login initiation, launch verified (`RS256` by key set, `iss`/`aud`/`azp`/`exp`/`iat`, `state` and `nonce` once, deployment) | Implemented and tested |
| Platform: launch started, authentication request answered with a signed `id_token`, key set | Implemented and tested |
| Messages `LtiResourceLinkRequest` and `LtiDeepLinkingRequest`, typed roles, context, resource link, presentation, platform, custom claims | Implemented, tested and fuzzed |
| Deep Linking 2.0: signed response of the tool, verified by the platform, five types of content items | Implemented, tested and fuzzed |
| Assignment and Grade Services: tool client, platform service | Implemented and tested |
| Names and Role Provisioning Service: tool client with pages, platform service | Implemented and tested |
| Access tokens by JWT assertion (RFC 7523), cached; token endpoint of the platform | Implemented and tested |
| Key sets read through `phil/safe-http`, cached, read again once for an unknown key | Implemented and tested |
| Ports and adapters in memory | Implemented and tested |
| LTI Dynamic Registration | Planned |
| Certification suite of 1EdTech | Planned, a step before the first stable release |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The tool](doc/tool.md) | Registering platforms, the login initiation, the launch and what it carries. |
| [The platform](doc/platform.md) | Registering tools, starting a launch, answering the authentication request, the key set. |
| [Deep Linking](doc/deep-linking.md) | The request, the content items, the response and its verification. |
| [Assignment and Grade Services](doc/ags.md) | Access tokens, line items, scores and results, on both sides. |
| [Names and Role Provisioning](doc/nrps.md) | The members of a context, on both sides. |
| [Keys](doc/keys.md) | Keys, key sets, rotation and the cache of the other side's keys. |
| [Security](doc/security.md) | What each check protects against, and what stays with the application. |
| [Extension points](doc/extension-points.md) | The ports, their adapters in memory, and what stays closed. |
| [Testing](doc/testing.md) | The package's tests, its fuzz targets, and the certification suite. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

A tool registered with one platform:

```php
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Key\CachedKeySetSource;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;
use Phil\Url\Url;

$platforms = new InMemoryPlatformRegistrations()->add(new PlatformRegistration(
    'https://lms.example.org',
    'client-id-given-by-the-platform',
    Url::fromString('https://lms.example.org/lti/authorize'),
    Url::fromString('https://lms.example.org/lti/token'),
    Url::fromString('https://lms.example.org/lti/jwks'),
), ['deployment-id']);

// 1. The platform calls the login initiation: send the browser to the platform.
$redirect = new LoginInitiator($platforms, $states, $clock)
    ->initiate(LoginRequest::fromParameters($_REQUEST), Url::fromString('https://tool.example.com/lti/launch'));
header('Location: '.$redirect->url->toString());

// 2. The platform posts the id_token and the state to the launch address.
$verifier = new JwtVerifier(new CachedKeySetSource(new SafeHttpKeySetSource(), $cache, $clock), $clock);
$launch = new LaunchValidator($platforms, $states, $nonces, $verifier)->validate($_POST['id_token'], $_POST['state']);

$launch->subject();                       // the person, for this platform and client
$launch->roles()->isInstructor();
$launch->message->context?->title;
$launch->message->resourceLink?->id;
```

## Requirements

PHP 8.5 or later with the JSON, mbstring, OpenSSL, sodium and URI extensions; `web-token/jwt-library`, `phil/url`,
`phil/safe-http`, Symfony's cache and service contracts, and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
