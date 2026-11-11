# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `phil/ujo`, `phil/ujo-bundle` | ^0.1 | The model, the checks, and the `Ujo` service and block type registry the field uses |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The bundle class and its configuration |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `BlockEditorType` |
| PHP's `mbstring` | — | Counting the characters of labels and queries |
| `symfony/http-foundation` | ^8.2 | The upload and search endpoints, the request's locale |
| `symfony/security-core` | ^8.2 | The access check of the endpoints, on the application's authorization checker |
| `symfony/security-csrf` | ^8.2 | The stateless CSRF check of the endpoints |
| `symfony/routing` | ^8.2, optional | The endpoints' routes, and their URLs given to the editor |
| `symfony/twig-bundle` | ^8.2, optional | The form theme |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | optional | Serving the controller and its stylesheet without a build step |
| Stimulus | 3, in the browser | The controller's base class |

## Installing

```sh
composer require phil/block-editor-bundle
```

Without Symfony Flex, enable both bundles in `config/bundles.php`:

```php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
    Phil\BlockEditorBundle\PhilBlockEditorBundle::class => ['all' => true],
];
```

The bundle adds its form theme to Twig's `form_themes`, so `form_row()` draws the editor with no further step.

## The controller and the stylesheet

With AssetMapper, the bundle maps `assets/src` as `@phil/block-editor-bundle` and `assets/styles` as
`phil-block-editor`. Symfony Flex registers the controller in `assets/controllers.json` from the package's
`assets/package.json`; without Flex, add it by hand:

```json
{
    "controllers": {
        "@phil/block-editor-bundle": {
            "editor": { "enabled": true, "fetch": "eager" }
        }
    },
    "entrypoints": []
}
```

Link the stylesheet in the layout of the pages that edit:

```twig
<link rel="stylesheet" href="{{ asset('phil-block-editor/block-editor.css') }}">
```

The controller imports only `@hotwired/stimulus`, which the application's importmap already has. With another
pipeline, import `assets/src/editor_controller.js` and register it under the name
`phil--block-editor-bundle--editor`.

## The routes

Image uploads and the `@` and `[[` searches go through three routes. Import them under a prefix, inside the firewall
that guards the forms:

```yaml
# config/routes/phil_block_editor.yaml
phil_block_editor:
    resource: '@PhilBlockEditorBundle/config/routes.php'
    prefix: /_block-editor
```

A route answers only when its service is configured ([configuration.md](configuration.md)); the field gives the
editor the URL of each one that does. The bundle adds the token `phil_block_editor` to FrameworkBundle's
`csrf_protection.stateless_token_ids`, which turns `framework.csrf_protection` on: leave it on
([integrations.md](integrations.md#guarding-the-endpoints)); with `csrf_protection: false`, the container stops with a
`LogicException` that says so. Without the routes, the editor takes image addresses only and leaves `@` and
`[[` as text.

A firewall alone does not require anyone to sign in: a lazy firewall, or one that lets anonymous visitors through,
reaches the routes for anyone. So each endpoint asks the application's authorization checker for the `access`
attribute, `IS_AUTHENTICATED` by default, and refuses with 403 whoever is not granted it, and everyone when
SecurityBundle is not installed. Name a stricter attribute when only some members write:

```yaml
# config/packages/phil_block_editor.yaml
phil_block_editor:
    access: ROLE_EDITOR
```

With `access: ~`, the bundle checks nothing and the application must guard the prefix itself, with an
`access_control` entry:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_block-editor, roles: ROLE_EDITOR }
```

The uploader and the searches are called for granted users only, but not told who they are: one that keeps images per
member, or shows only what a member may see, reads the current user from Symfony's `Security` service
([integrations.md](integrations.md#guarding-the-endpoints)).
