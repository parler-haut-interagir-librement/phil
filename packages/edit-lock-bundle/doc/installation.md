# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 with FrameworkBundle and its CSRF protection (`symfony/security-csrf`), DoctrineBundle
with DBAL 4 and ORM 3, a `clock` service
(`symfony/clock`), and `phil/ujo`. `phil/ujo-bundle` is recommended: saved blocks are then checked with the
application's own block types. To push presence and saves, MercureBundle and a Mercure hub. In the browser, Stimulus 3
served by `symfony/stimulus-bundle` and AssetMapper, and the block editor of `phil/block-editor-bundle`.

## Enabling the bundle

```sh
composer require phil/edit-lock-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\EditLockBundle\PhilEditLockBundle::class => ['all' => true],
];
```

## The tables

The bundle maps `Phil\EditLockBundle\Domain\Model\Lease` and `LeasedResource` for Doctrine, as the tables
`phil_edit_lease` and `phil_edit_lease_resource`: create them with the application's migrations.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Column | Type | Role |
|---|---|---|
| `resource` | `VARCHAR(255)`, primary key | The resource, as the application names it |
| `part` | `VARCHAR(64)`, primary key | The block's identifier; empty for the whole resource |
| `holder_id`, `holder_name` | `VARCHAR(255)` | Who holds it, and the name the others see |
| `acquired_at`, `expires_at` | `DATETIME`, UTC | Since and until when; `expires_at` is indexed |
| `version` | `INTEGER` | One more at each change, for compare-and-set |

`phil_edit_lease_resource` holds one row per resource ever leased, the row each claim locks
([races](leases.md#races)):

| Column | Type | Role |
|---|---|---|
| `resource` | `VARCHAR(255)`, primary key | The resource, as the application names it |
| `claims` | `INTEGER` | How many claims ran on it |

## The application's services

The endpoints answer only once the application has said who writes and what they may edit:

```yaml
# config/packages/phil_edit_lock.yaml
phil_edit_lock:
    holder_provider: App\Collaboration\CurrentWriter
    policy: App\Collaboration\WritersPolicy
```

```php
use Phil\EditLockBundle\Application\HolderProvider;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentWriter implements HolderProvider
{
    public function __construct(private Security $security) {}

    public function holder(): ?Holder
    {
        $user = $this->security->getUser();

        return null === $user ? null : Holder::of($user->getUserIdentifier(), $user->getUserIdentifier());
    }
}
```

The policy is described in [leases](leases.md), the block store in [blocks](blocks.md).

## The routes

```yaml
# config/routes/phil_edit_lock.yaml
phil_edit_lock:
    resource: '@PhilEditLockBundle/config/routes.php'
    prefix: /_edit-lock
```

Put the prefix behind the application's firewall. The routes are listed in [endpoints](endpoints.md). The bundle adds
the token `phil_edit_lock` to FrameworkBundle's `csrf_protection.stateless_token_ids`, which turns
`framework.csrf_protection` on: leave it on.
With `csrf_protection: false`, the container stops with a `LogicException` that says so.

## Mercure

With MercureBundle registered, the bundle publishes to its default hub. Updates are private: each writer's browser
needs the right to subscribe to the topic of the resource, which the application grants as MercureBundle explains,
for instance with its authorization cookie:

```php
$authorization->setCookie($request, [$config['topic']]); // the topics this writer may subscribe to
```

[Mercure](mercure.md) has the topics, the messages and the options.

## The controller and the stylesheet

With AssetMapper, the bundle maps its controller under `@phil/edit-lock-bundle` and its stylesheet under
`phil-edit-lock`. Enable the controller in `assets/controllers.json`:

```json
{
    "controllers": {
        "@phil/edit-lock-bundle": {
            "collaboration": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-edit-lock/edit-lock.css') }}">
```

[JavaScript](javascript.md) shows the markup and the configuration.
