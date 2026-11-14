# phil/onboarding-bundle

**The welcome of a new member, for Symfony: steps in order, a checklist and a guided tour.**

phil/onboarding-bundle walks a new member through the first things to do on a site. The application and its packages
declare onboarding flows, by configuration or attribute: ordered steps, each with a title, the route it leads to, the
element of the page a guided tour points out, and how it gets done — ticked by the person, completed when a Symfony
event is dispatched, completed when a checker of the application says so, or answered: a question whose choices the
application turns into roles and subscriptions, through a port, the bundle never writing them itself. The bundle keeps
where each user stands, step by step, and shows it two ways: a checklist that works without JavaScript, and a guided
tour that highlights each step's target in turn. A person may skip a step, put a flow away and start it again. Labels
come in English and French.

It is for a site that opens to new members, authors or customers and wants them to find their way: filling in a
profile, writing a first page, joining a group. The progress is kept under the user identifier the application names
its users by, whatever its user class, so the bundle works with Symfony Security or any other sign-in.

The bundle is built on Doctrine ORM, Symfony's dependency injection, HTTP foundation and stateless CSRF protection.
Twig, Symfony UX Twig Component, the Stimulus bundle, AssetMapper, Symfony Security and `phil/settings-bundle` are
optional: the checklist and the tour are semantic HTML whose buttons are the recipe `Button` of the kit, installed in
the application by the Symfony UX Toolkit; with the settings bundle, each user may turn the tours off. The
`Onboarding` service sits on the `ProgressRepository` port, with Doctrine and in-memory stores; it keeps no state
between requests and is safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Flows and steps by configuration and attribute, ordered by `before` and `after`, checked when the container is built | Implemented and tested |
| Completion by hand, by a Symfony event, by a checker of the application, by an answer | Implemented and tested |
| Questions whose choices grant roles and subscriptions through `AnswerGrants` | Implemented and tested |
| `Progress`, kept with Doctrine or in memory, one row per user and flow | Implemented and tested |
| `Onboarding`: progress, complete, mark done, answer, skip, dismiss, reset, forget; events made of identifiers | Implemented and tested |
| Endpoints that tick, answer, skip, dismiss and reset, with a stateless CSRF token | Implemented and tested |
| The checklist and the tour: Twig functions, the components `PhilOnboarding:Checklist` and `PhilOnboarding:Tour`, the Stimulus controller and its stylesheet, the recipe `Button` of the kit, English and French | Implemented and tested |
| The setting `onboarding.tours` of `phil/settings-bundle` | Implemented and tested |
| The export and erasure of a user's progress through `phil/privacy-bundle` | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the Flex recipe, the table, the routes, the recipe of the kit, the assets. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Flows and steps](doc/flows.md) | Declaring flows and steps, their order, the four completions and questions, reading and changing the progress. |
| [The checklist and the tour](doc/display.md) | The Twig functions and components, the controller, the keyboard, screen readers and reduced motion, the look. |
| [Events](doc/events.md) | The events dispatched, and what they carry. |
| [Extension points](doc/extension-points.md) | The ports, the declarations, the services and the events. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test code that uses flows. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/onboarding-bundle
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_onboarding.yaml
phil_onboarding:
    flows:
        author:
            title: onboarding.author.title
            steps:
                profile: { title: onboarding.author.profile, route: app_profile, target: '#profile-form' }
                first-page: { title: onboarding.author.first_page, completion: event, event: App\Event\PageWritten }
```

```twig
<twig:PhilOnboarding:Checklist flow="author" />
<twig:PhilOnboarding:Tour flow="author" />
```

```php
$onboarding->complete($user->getUserIdentifier(), 'author', 'profile');
$onboarding->progress($user->getUserIdentifier(), 'author')->percent();   // 50
```

## Requirements

PHP 8.5 or later with `mbstring`; Doctrine ORM 3.7 and DBAL 4.5; Symfony 8.2's config, dependency injection, HTTP
foundation and CSRF protection; Symfony's service contracts 3.6; PSR-20's clock and PSR-14's event dispatcher. Twig,
Symfony Routing, Symfony UX Twig Component with the recipe `Button` of the kit, the Stimulus bundle, AssetMapper,
Symfony Security and `phil/settings-bundle` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
