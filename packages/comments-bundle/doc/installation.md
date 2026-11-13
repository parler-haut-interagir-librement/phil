# Installation

## Requirements

PHP 8.5 or later with `mbstring`, Symfony 8.2 with FrameworkBundle and its CSRF protection (`symfony/security-csrf`),
DoctrineBundle with ORM 3, a `clock` service (`symfony/clock`), `phil/resource` and `phil/ujo`. In the browser, Stimulus
3 served by `symfony/stimulus-bundle` and AssetMapper, and the block editor of `phil/block-editor-bundle`. Highlighting
passages in the editor uses the CSS Custom Highlight API, which current browsers have; without it, threads are listed
but not highlighted. For the comments on a page drawn by the server, Symfony UX Twig Component 3.7 with TwigBundle and
the translator, and the recipes of the PHIL UX kit.

## Enabling the bundle

```sh
composer require phil/comments-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\CommentsBundle\PhilCommentsBundle::class => ['all' => true],
];
```

## The tables

The bundle maps `Phil\CommentsBundle\Domain\Model\Thread` and `Comment` for Doctrine, as the tables
`phil_comment_thread` and `phil_comment`: create them with the application's migrations.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Table | Column | Type | Role |
|---|---|---|---|
| `phil_comment_thread` | `id` | `VARCHAR(36)`, primary key | A UUID v7 |
| | `subject` | `VARCHAR(320)`, indexed | The resource commented on, `kind:identifier` |
| | `block`, `field` | `VARCHAR(64)`, `VARCHAR(1039)`, nullable | Where the passage is; both null in a plain text and on the whole page |
| | `start_offset` | `INTEGER`, nullable | The passage's first character in the field's text; null on the whole page |
| | `quote` | `TEXT`, nullable | The passage; null for a thread on the whole page |
| | `prefix`, `suffix` | `VARCHAR(255)` | Up to 32 characters on each side of the passage, empty on the whole page |
| | `detached` | `BOOLEAN` | Whether the passage has disappeared: the thread is orphaned |
| | `status` | `VARCHAR(16)` | `open` or `resolved` |
| | `author` | `VARCHAR(320)` | Who opened it, `kind:identifier` |
| | `suggestion`, `suggestion_state` | `TEXT`, `VARCHAR(16)`, nullable | The proposed replacement and `pending`, `accepted` or `rejected` |
| | `resolved_by`, `resolved_at`, `created_at` | `VARCHAR(320)`, `DATETIME` UTC | Who resolved it, when, and when it was opened |
| `phil_comment` | `id` | `VARCHAR(36)`, primary key | A UUID v7 |
| | `thread` | `VARCHAR(36)`, indexed | The thread's identifier |
| | `author`, `body` | `VARCHAR(320)`, `TEXT` | Who wrote it, and its plain text |
| | `created_at`, `edited_at` | `DATETIME` UTC | When it was written and last edited |

## The application's services

The endpoints answer only once the application has said who comments and what they may do:

```yaml
# config/packages/phil_comments.yaml
phil_comments:
    author_provider: App\Review\CurrentAuthor
    policy: App\Review\ReviewPolicy
    author_directory: App\Review\AuthorNames   # optional
```

```php
use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentAuthor implements AuthorProvider
{
    public function __construct(private Security $security) {}

    public function author(): ?ResourceReference
    {
        $user = $this->security->getUser();

        return null === $user ? null : ResourceReference::of('user', $user->getUserIdentifier());
    }
}
```

The policy and the directory are described in [threads](threads.md).

## The routes

```yaml
# config/routes/phil_comments.yaml
phil_comments:
    resource: '@PhilCommentsBundle/config/routes.php'
    prefix: /_comments
```

The plain forms of the comments on a page have a route of their own, imported beside the endpoints when the page
draws them:

```yaml
phil_comments_page:
    resource: '@PhilCommentsBundle/config/page_routes.php'
    prefix: /_comments
```

Put the prefix behind the application's firewall. The routes are listed in [endpoints](endpoints.md). The bundle adds
the token `phil_comments` to FrameworkBundle's `csrf_protection.stateless_token_ids`, which turns
`framework.csrf_protection` on: leave it on.
With `csrf_protection: false`, the container stops with a `LogicException` that says so.

## The controller and the stylesheet

With AssetMapper, the bundle maps its controller under `@phil/comments-bundle` and its stylesheet under
`phil-comments`, in the configuration of AssetMapperBundle; without that bundle, nothing is mapped and the container
still compiles. Enable the controller in `assets/controllers.json`; it is fetched lazily, only on the pages whose markup
uses it:

```json
{
    "controllers": {
        "@phil/comments-bundle": {
            "comments": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-comments/comments.css') }}">
```

[JavaScript](javascript.md) shows the markup and the configuration.

## The recipes of the kit

The comments on a page drawn by the server use recipes of the PHIL UX kit, installed in the application by the Symfony
UX Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

They have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them from that
kit instead. See [the page](page.md).
