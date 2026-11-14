# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3 and its bundle, `phil/resource`, `phil/ujo` and
`phil/notifications-bundle`. Twig 3 for the filter. Stimulus 3, AssetMapper and the routes of
`phil/block-editor-bundle` for the controller.

## Enabling the bundle

```sh
composer require phil/mentions-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
    Phil\MentionsBundle\PhilMentionsBundle::class => ['all' => true],
];
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_mentions.yaml`, which
names `App\Mentions\Members` as the directory, and copies that class to `src/Mentions/Members.php`. Until the
application makes it read its accounts, it finds nobody: no handle is linked, nobody is notified, the `@` search lists
no one, and the application boots. The recipe lives in the recipes repository of PHIL, read once the application lists
that repository in `extra.symfony.endpoint`; without it, register the bundle and write the configuration by hand, as
below.

## The table

The bundle maps `phil_mention` for Doctrine; the application's migrations create it:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Column | Holds |
|---|---|
| `id` | A UUID v7 |
| `subject` | What mentions the member, `comment:7` |
| `member` | The member mentioned, `user:42` |
| `author` | Who wrote the mention, `user:9` |
| `created_at` | When it was first recorded, to the second |

A subject mentions a member once, `(subject, member)` being unique; `member` is indexed for the mentions of a member.

## The directory

The application writes one service, a `MemberDirectory`, and names it:

```php
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Member;
use Phil\Resource\ResourceReference;

final readonly class Members implements MemberDirectory
{
    public function __construct(private UserRepository $users, private Access $access) {}

    public function resolve(array $handles): iterable
    {
        foreach ($this->users->withHandles($handles) as $user) {
            yield $this->member($user);
        }
    }

    public function members(array $references): iterable
    {
        foreach ($this->users->withIds(array_map(static fn (ResourceReference $r): string => $r->id, $references)) as $user) {
            yield $this->member($user);
        }
    }

    public function search(string $query, int $limit): iterable
    {
        foreach ($this->users->startingWith($query, $limit) as $user) {
            yield $this->member($user);
        }
    }

    public function maySee(ResourceReference $member, ResourceReference $content): bool
    {
        return $this->access->maySee($member->id, $content);
    }

    private function member(User $user): Member
    {
        return new Member(ResourceReference::of('user', (string) $user->id), $user->handle, $user->name, '/members/'.$user->handle);
    }
}
```

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    member_directory: App\Members\Members
```

## The other bundles

Each is optional, and configured by the bundle when it is enabled:

| Bundle | What it brings |
|---|---|
| `phil/block-editor-bundle` | The editor's `@` menu searches the directory, and the controller reads that search |
| `phil/comments-bundle` | `CommentMentions`, and `MemberAuthorDirectory` to name comment authors |
| `phil/settings-bundle` | The setting `notifications.mentions`, which the members turn off |
| `phil/markup-bundle` | Markdown rendered before `mentions` links its handles |

See [Integrations](integrations.md).

## The controller and the stylesheet

With AssetMapper and the Stimulus bundle, the controller is served as `@phil/mentions-bundle/mention_controller.js`
and the stylesheet as `phil-mentions/mentions.css`, both mapped in the configuration of AssetMapperBundle; without that
bundle nothing is mapped and the container still compiles. The controller is fetched lazily, only on the pages whose
markup uses it:

```json
// assets/controllers.json
{
    "controllers": {
        "@phil/mentions-bundle": {
            "mention": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-mentions/mentions.css') }}">
```
