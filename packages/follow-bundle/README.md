# phil/follow-bundle

**Follow a content item, a thread, a listing or a member, at the level each member chooses, and hear of new activity
through phil/notifications-bundle: requests for locked profiles, mutual relationships, followers hidden on request.**

phil/follow-bundle is a Symfony bundle. A member follows a subject — any record of the application, named by a type and
an identifier — at one of four levels: **watching**, **mentions only**, **ignoring**, or **custom**, a level for each
kind of event. Following a parent follows its children, unless the member chose otherwise on a nearer subject. When the
application publishes an activity, the bundle sends it to the followers through `phil/notifications-bundle`, a bounded
page at a time, by Messenger messages that carry identifiers only, and never twice to the same member.

```php
$follows->follow($member, new SubjectReference('thread', $thread->id));
$activities->publish(new SubjectReference('thread', $thread->id), 'reply.created', $author, $mentioned, 'reply:'.$reply->id);
```

## Features

- **Levels**: watching, mentions only, ignoring — which also stops what a followed parent brings —, and custom by
  event; see [Following](doc/following.md).
- **Inheritance**: the application names the parents of a subject; the nearest follow decides.
- **Requests**: a locked profile, or any subject the application approves, turns a follow into a request that its
  approver accepts or rejects — `Follow`, `Accept` and `Reject`, the words of ActivityPub.
- **Mutual relationships**, turned on by configuration, beside one-way following; stopping to follow keeps them.
- **Delivery** through `phil/notifications-bundle`: pages of followers, claims per member and activity so that a retry
  sends to no one twice, one notification on every channel until the member sees the subject, and the digest of the
  notifications bundle; see [Delivery](doc/delivery.md).
- **Automatic follows**, when turned on, of what a member creates or replies to.
- **Privacy**: followers hidden at each member's choice, never shown by the API nor the templates; export and erasure
  through phil/privacy-bundle; see [Privacy](doc/privacy.md).
- **Templates** that work without JavaScript: the follow button and its menu of levels, the requests, the followers, the
  visibility of one's followers, the mutual relationship; see [Templates](doc/templates.md).
- No activity feed: each application composes its home page from the follows and the notifications; see
  [A home page](doc/home-page.md).

## Status

| Part | State |
|---|---|
| Follows, levels, preferences by event, inheritance | Implemented and tested |
| Requests, mutual relationships, automatic follows | Implemented and tested |
| Delivery by pages, claims, hold until seen, purge schedule | Implemented and tested |
| Followers' visibility, export and erasure | Implemented and tested |
| Templates and forms without JavaScript | Implemented and tested |
| Flex recipe | Not written yet |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables and the routes. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Following](doc/following.md) | Levels, preferences by event, inheritance, requests, mutual relationships, automatic follows. |
| [Delivery](doc/delivery.md) | Publishing an activity, the pages, the claims, the hold until seen, the digest and the purge. |
| [Templates](doc/templates.md) | The templates, their Twig functions and their forms. |
| [A home page](doc/home-page.md) | How an application composes a member's home page from the follows and the notifications. |
| [Privacy](doc/privacy.md) | Hidden followers, the export and the erasure. |
| [Extension points](doc/extension-points.md) | The ports the application writes and the services it calls. |
| [Testing](doc/testing.md) | The bundle's tests. |
| [Reference](doc/reference.md) | Every public type of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\FollowBundle\PhilFollowBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_follow.yaml
phil_follow:
    access: App\Follow\SubjectAccess   # required with Symfony Security
```

```yaml
# config/routes/phil_follow.yaml
phil_follow:
    resource: '@PhilFollowBundle/config/routes.php'
    prefix: /follow
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine DBAL 4.5 or later and phil/notifications-bundle 0.3 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
