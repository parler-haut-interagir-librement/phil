# phil/terms-bundle

**Versioned terms of use for Symfony: each version published once, each acceptance kept, and a member asked again when
the terms change.**

phil/terms-bundle keeps the policies a site asks its members to accept — its terms of use, its privacy policy, its
code of conduct — as versions that are published once and never changed: a number, the text of each locale, the moment
it was published, the moment it takes effect, and whether it changes the terms enough that every member must accept it
again. Each acceptance is a record that is only ever added to: who, which version, when, where, and — when the
application wants it — the network the request came from, never the address. A gate sends a signed-in member who has
a material version to accept to a page where each version comes with its own checkbox, never ticked in advance; an API
is answered `403` with a problem in JSON. A signup form gets the same checkbox from one option. An acceptance can be
asked again after some days, and reminders go out on a schedule. One policy can name the licence of the members'
contributions, chosen with each version and dated by its acceptance. Every version keeps a public address, with the
changes from the version before; an administration publishes them, and a command lists who has not accepted.

It is for any site that opens to the public and must show which terms each member accepted, and when.

## Status

| Part | State |
|---|---|
| Policies, immutable versions with their locales, moments and material flag | Implemented and tested |
| Acceptances, append-only, with their channel and, optionally, the network | Implemented and tested |
| The gate on every request, the acceptance page, the problem of an API | Implemented and tested, benchmarked |
| The signup checkbox, the version shown checked at submission | Implemented and tested |
| Periodic renewal, reminders on a schedule, notices of a new version | Implemented and tested |
| The licence of the contributions, chosen with a version and dated by its acceptance | Implemented and tested |
| Public versioned pages and the changes between two versions | Implemented and tested |
| The administration in Twig, in English and French, and the EasyAdmin menu | Implemented and tested |
| The pages drawn by the recipes of the PHIL UX kit or the Shadcn kit, each rendered with both in the tests | Implemented and tested |
| The moments kept in UTC, the administration showing them in its time zone | Implemented and tested |
| `phil:terms:outstanding`, `phil:terms:check-legal-links`, the export and the erasure of the acceptances | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes, and the two classes that name the members | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the tables, the recipe, the routes. |
| [Configuration](doc/configuration.md) | Every option of `phil_terms`. |
| [Policies and versions](doc/versions.md) | Policies, versions, material changes, the moment a version takes effect, the texts. |
| [Acceptance](doc/acceptance.md) | What a member has to accept, the gate, the acceptance page, APIs, the signup form, recording from code. |
| [Renewal and reminders](doc/renewal-and-reminders.md) | Acceptances asked again, the reminders, the notices of a new version. |
| [Contribution licence](doc/contribution-licence.md) | The licence of the members' contributions, chosen with a version and dated by its acceptance. |
| [Public pages](doc/public-pages.md) | The versioned addresses, the changes, the cache, the recipes of the kit. |
| [Administration](doc/administration.md) | Publishing a version, the role, the token, the layout, EasyAdmin. |
| [Console](doc/console.md) | `phil:terms:outstanding` and `phil:terms:check-legal-links`. |
| [Personal data](doc/personal-data.md) | What an acceptance keeps, the network, the export and the erasure. |
| [Extension points](doc/extension-points.md) | The ports, services and options an application chooses or replaces. |
| [Testing](doc/testing.md) | The tests, the mutation bar and the benchmark. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/terms-bundle
```

```yaml
# config/packages/phil_terms.yaml
phil_terms:
    locales: [en, fr]
    policies:
        terms: ~
        privacy: { renew_after_days: 365 }
    member_resolver: App\Security\MemberId
    member_source: App\Security\EveryMember
    admin: { enabled: true, role: ROLE_LEGAL }
```

```yaml
# config/routes/phil_terms.yaml
phil_terms:
    resource: '@PhilTermsBundle/config/routes/pages.php'
    prefix: /terms
phil_terms_admin:
    resource: '@PhilTermsBundle/config/routes/admin.php'
    prefix: /admin/terms
```

```php
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\AcceptanceChannel;

$terms->publish('terms', ['en' => $english, 'fr' => $french]);
$terms->accept($member->id, 'terms', 1, AcceptanceChannel::Api);
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine DBAL 4.5 or later, Twig 3.21 or later and Symfony UX Twig Component
3.7 or later, with the recipes of the PHIL UX kit — or of the Shadcn kit — installed by the Symfony UX Toolkit.
Doctrine ORM, the console, the form component, the scheduler, the translator, EasyAdmin, `phil/ip-anonymizer`,
`phil/legal-notices-bundle`, `phil/notifications-bundle`, `phil/privacy-bundle`, `phil/text-diff` and `phil/ujo` are
optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
