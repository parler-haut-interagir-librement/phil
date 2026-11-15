# phil/moderation-bundle

**One queue for every report on a site — forum posts, comments, reactions, listings, messages, profiles —, the
decisions of its moderators and their history, a statement of reasons to the author of each restricted content, the
hooks of the first-post sandbox and the watched words, and a public journal that shows the role of a moderator and
never their name.**

phil/moderation-bundle gives a Symfony site a single place where its moderators review what members report. Each
package that holds content plugs in its own `ModerationTarget` — what the queue shows of a content, and what a
decision does to it — and every report, whatever the content, lands in the same queue. A moderator acts under a role
of the site's configuration, which may cover every type of content or only some; their decision — dismiss, approve,
hide, restore, remove, warn — is applied by the content's package, closes the open reports, and joins the history of
the content and of its author. A decision that restricts the author comes with a statement of reasons: the reason,
whether it rests on the terms of the site or on the law, the facts, whether a report or automated detection prompted
it, and the ways to contest it, which the author reads on their own page and may be notified of.

The same queue receives what the site holds back on its own: a package calls `Submissions::submit()` when a member
posts, and the watched words block, hold or flag the text, while the sandbox of the first post holds the first
content of a newcomer until a moderator approves one. Members of a high trust level may hide a content at once, until
the moderation reviews it. Moderators keep notes on a member, which the member never reads. The public journal lists
the public decisions with the role they were taken under. When `phil/incident-mode` 0.2 is installed, the back office
offers the incident mode, and proposes it when reports surge; an incident may spare the members the site holds for
established.

Members are designated by a stable identifier, never an e-mail address. The records live in five tables of a Doctrine
DBAL connection, their moments in UTC, and join a member's export and erasure through `phil/privacy-bundle`. The
pages answer on the bundle's routes and, with EasyAdmin, inside every dashboard; they draw their parts with recipes of
the PHIL UX kit.

## Status

| Part | State |
|---|---|
| One queue for every registered target type; a report per member and target while it is open; reasons from the configuration | Implemented and tested |
| Roles of the moderation: the first one held, the target types each covers | Implemented and tested |
| Decisions applied by the target's package, closing the open reports; history per target and per member | Implemented and tested |
| Statements of reasons: grounds checked before the action, terms or law by reason, report and automated detection, redress; the member's pages; the notification with `phil/notifications-bundle` | Implemented and tested |
| `Submissions`: the hook of the watched words (block, hold, flag, replace) and of the first-post sandbox | Implemented and tested |
| Temporary hiding by trusted members, reviewed by the moderation | Implemented and tested |
| Profiles as a target, through the application's `MemberProfiles` | Implemented and tested |
| Notes of the moderation on a member | Implemented and tested |
| The public journal: role, never name | Implemented and tested |
| The incident mode offered, and proposed when reports surge, with `phil/incident-mode` | Implemented and tested |
| Restrictions of an incident that spare established members | Implemented and tested; who is established comes through the port `EstablishedMembers` of `phil/incident-mode`, which the application implements |
| The pages, on the bundle's routes and inside EasyAdmin dashboards; the report form; the recipes of the kit | Implemented and tested |
| Export and erasure through `phil/privacy-bundle` | Implemented and tested |
| DBAL tables on PostgreSQL, MySQL and SQLite, moments in UTC, ORM schema listener | Implemented and tested |
| Watched words from `phil/watched-words` | Port only: `WordScreen`; the adapter comes with that package |
| Trust levels from `phil/trust-levels` | Ports only: `TrustedHiders`, and `EstablishedMembers` of `phil/incident-mode`; the adapters come with that package |
| Reports on Fediverse content and the `Flag` activity | Not started |
| The appeal of a decision, reviewed inside the bundle by another role than the one that decided | Not started: the statement of reasons links to the complaint page the site configures |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the tables, the routes, the recipes, the members. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The queue](doc/queue.md) | Targets, reports, roles, decisions and their history. |
| [Hooks](doc/hooks.md) | The first-post sandbox, the watched words, temporary hiding by trusted members, profiles. |
| [Statements of reasons](doc/statements-of-reasons.md) | The grounds of a restriction, what the author reads, the notification, the redress. |
| [Pages](doc/pages.md) | The back office, EasyAdmin, the notes on members, the report form, the public journal. |
| [Incident mode](doc/incident-mode.md) | What the bundle wires when `phil/incident-mode` is installed. |
| [Personal data](doc/privacy.md) | Stable identifiers, the export and the erasure. |
| [Extension points](doc/extension-points.md) | The ports, tags and events, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, its benchmark, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_moderation.yaml
phil_moderation:
    roles:
        - { name: moderator, attribute: ROLE_MODERATOR }
    sandbox:
        types: [forum.post]
```

```twig
{{ include('@PhilModeration/_report_form.html.twig', {type: 'forum.post', id: post.id}) }}
```

## Requirements

PHP 8.5 or later with `ext-sodium`, Symfony 8.2 or later, Doctrine DBAL 4.5, Twig and Symfony UX Twig Component 3.7.
SecurityBundle for the pages of the moderation.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
