# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later, with mbstring and XMLWriter | The checks of the entries, the Atom and RSS writers |
| `phil/url` | ^0.1 | Every address: the pages, the entries, the hubs |
| `phil/publishing` | ^0.1 | `PagePublished` and `PageRetired`, which announce a feed |
| `phil/safe-http` | ^0.2 | The requests to the hubs |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | The served feeds |
| `symfony/messenger` | ^8.2 | The announcements to the hubs |
| `twig/twig` | ^3.21 | `phil_feed_links()` |
| `psr/log` | ^3.0 | The refusals and failures of the hubs |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `phil/content-bundle` | ^0.3, optional | The content feeds |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\FeedBundle\PhilFeedBundle::class => ['all' => true],
];
```

The Twig function needs TwigBundle; the announcements need FrameworkBundle's Messenger. Without a hub configured,
Messenger is not used.

## Routes

```yaml
# config/routes/phil_feed.yaml
phil_feed:
    resource: '@PhilFeedBundle/config/routes.php'
```

| Route | Path | Methods |
|---|---|---|
| `phil_feed` | `/feeds/{name}.{format}`, `format` being `atom`, `rss` or `json` | `GET`, `HEAD` |

A `prefix` on the import moves the feeds, as `/fr/feeds/…`; the address each feed names, the links of a page and the
topics told to the hubs follow the route.

## The Flex recipe

The recipe in `recipe/0.1` registers the bundle, adds `config/packages/phil_feed.yaml` with the defaults commented and
`config/routes/phil_feed.yaml` with the routes.
