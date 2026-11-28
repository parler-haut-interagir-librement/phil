# Installation

```sh
composer require phil/svg-blocks-bundle
```

Without Flex, enable the bundle after `phil/ujo-bundle`:

```php
// config/bundles.php
Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
Phil\SvgBlocksBundle\PhilSvgBlocksBundle::class => ['all' => true],
```

## The preview route

```yaml
# config/routes/phil_svg_blocks.yaml
phil_svg_blocks:
    resource: '@PhilSvgBlocksBundle/config/routes.php'
    prefix: /_svg-blocks
```

Put the prefix behind the firewall of the writers. The preview needs FrameworkBundle's CSRF protection, on by
default; the bundle adds its token to the stateless ones, so no session is needed.

## The controller

With AssetMapper and the Stimulus bundle, the controller is mapped as `@phil/svg-blocks-bundle` in AssetMapperBundle's
configuration, and enabled from `assets/controllers.json` like any UX package. It is fetched when its first element
appears on a page:

```json
"@phil/svg-blocks-bundle": {"preview": {"enabled": true, "fetch": "lazy"}}
```

## The Mermaid service

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
```

Run it beside the application, on a network that reaches nothing else, with the limits of
`docker/mermaid/compose.yaml`, and set `phil_svg_blocks.mermaid.url`. [Diagrams](diagrams.md) holds the conditions
its security review sets.
