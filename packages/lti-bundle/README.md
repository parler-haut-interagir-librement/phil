# phil/lti-bundle

**`phil/lti` in Symfony: an LTI 1.3 tool, a platform, or both, from one configuration — the routes of the launch, a
security token for each verified launch, the services of LTI Advantage, stores in the database and keys that rotate.**

phil/lti-bundle brings `phil/lti` into a Symfony application:

- **as a tool**: the routes of the login initiation and of the launch, an authenticator that turns a verified launch into
  an `LtiToken` carrying it, an event to answer the launch, the Deep Linking response signed and posted back, and the
  scores sent to the platform through Messenger, the message carrying an identifier only, those never dispatched sent
  again by a command and those refused for good kept as a trace;
- **as a platform**: the authorization endpoint that signs a launch for the member signed in, the return of Deep
  Linking, kept before the application handles it so that a failed handling resumes, the token endpoint, the Assignment
  and Grade Services and the Names and Role Provisioning Service over HTTP, the latest score kept by one atomic write,
  and events for the application's gradebook;
- **for both**: the key set route, the nonces, login states, access tokens, line items, scores and pending scores in
  tables of a Doctrine DBAL connection, tested on SQLite, MySQL 8.4 and PostgreSQL 17; the keys made, sealed by Symfony's
  KeyManagement and rotated by a command, or read from the environment; the form that posts a launch, drawn with the
  recipe `Button` of the kit and posted by an inline script under the nonce of its content security policy.

It is for Symfony applications that open other tools to their learners, or that learning platforms open: a course site,
a learning management system, a quiz, a lab, a reading tool.

It is built on what Symfony ships: Config and DependencyInjection for the configuration, Routing and HttpFoundation for
the routes, Security for the authenticator, Doctrine DBAL for the stores, KeyManagement for the sealed keys, Messenger
for the scores, Twig and the UX Toolkit recipes for the form, AssetMapper and Stimulus for the controller of a page of the application's own.

## Status

| Part | State |
|---|---|
| Configuration `phil_lti`: tool, platform or both, registrations, keys, stores | Implemented and tested |
| Tool: login initiation, launch, authenticator and `LtiToken`, `LaunchReceived` | Implemented and tested |
| Tool: Deep Linking response posted back, scores sent through Messenger or at once, `phil:lti:recover-scores` | Implemented and tested |
| Platform: authorization endpoint, Deep Linking return kept and resumed by `phil:lti:resume-deep-linking`, token endpoint | Implemented and tested |
| Platform: Assignment and Grade Services, Names and Role Provisioning Service, events | Implemented and tested |
| Stores on Doctrine DBAL, the schema listener, `phil:lti:schema` and `phil:lti:purge` | Implemented and tested on SQLite, MySQL 8.4 and PostgreSQL 17 |
| Keys sealed by Symfony KeyManagement, rotated by `phil:lti:rotate-keys`; keys from the environment | Implemented and tested |
| The form of a launch with the recipe `Button` of the kit, its inline script under a nonce, and its Stimulus controller | Implemented and tested |
| Registrations kept in the database, LTI Dynamic Registration | Planned |
| A gradebook of the platform | Left to the application, which listens to the events |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, the routes, the firewalls, the tables, the kit and the assets. |
| [Configuration](doc/configuration.md) | Every key of `phil_lti`, with an example. |
| [The tool](doc/tool.md) | Launches, the token and the user, answering a launch, Deep Linking, sending scores. |
| [The platform](doc/platform.md) | Launches, the application's `LaunchMessages`, Deep Linking returns, the services and their events. |
| [Keys](doc/keys.md) | Keys in the table sealed by the KMS, their rotation, keys from the environment. |
| [Storage](doc/storage.md) | The eight tables, their columns, the upgrade from 0.1 and the purge. |
| [Security](doc/security.md) | Firewalls, cookies in frames, the checks of the bundle, and what stays with the application. |
| [Extension points](doc/extension-points.md) | The ports, the events, the services to use, and what stays closed. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and testing an application. |
| [Reference](doc/reference.md) | Every class, route, service, command and event of the bundle. |

## Quick start

```sh
composer require phil/lti-bundle
bin/console doctrine:migrations:diff     # the eight tables
bin/console doctrine:migrations:migrate
```

```yaml
# config/packages/phil_lti.yaml
phil_lti:
    keys: { source: dbal, master_key: lti }
    tool:
        enabled: true
        platforms:
            - issuer: 'https://lms.example.org'
              client_id: '%env(LTI_CLIENT_ID)%'
              authorization_url: 'https://lms.example.org/lti/authorize'
              token_url: 'https://lms.example.org/lti/token'
              key_set_url: 'https://lms.example.org/lti/jwks'
              deployments: ['1']
```

```php
use Phil\LtiBundle\Application\Event\LaunchReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class OpenTheCourse
{
    public function __invoke(LaunchReceived $event): void
    {
        $context = $event->launch->message->context;
        // set $event->setResponse(...) or let the bundle redirect to the target of the launch
    }
}
```

## Requirements

PHP 8.5 or later with the OpenSSL extension; `phil/lti`, which signs through `web-token/jwt-library`, `phil/url` and
`phil/safe-http`; Symfony 8.2 Config, DependencyInjection, HttpFoundation,
Routing, Security and Twig; Doctrine DBAL 4. DoctrineBundle, the ORM, Console, KeyManagement, Messenger, AssetMapper
and the UX Twig components are optional: each part is registered when its package is there.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
