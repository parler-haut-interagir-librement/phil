# Installation

```sh
composer require phil/social-cards-bundle
```

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later, with GD and FreeType | The cards |
| `phil/social-cards` | ^0.1 | The metadata, the tags, the cards, the reader |
| `symfony/http-kernel`, `symfony/http-foundation`, `symfony/routing` | ^8.2 | The page's request, the card's route |
| `phil/safe-http` | ^0.1 | The guarded client of the previews |
| `symfony/cache-contracts` | ^3.6 | The cache of the previews |
| `twig/twig` | ^3.21 | `social_meta()` and `social_page()` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `symfony/messenger` | ^8.2, optional | The cards drawn by a worker |
| `symfony/console` | ^8.2, optional | `phil:social-cards:check` |
| `symfony/ux-twig-component` | ^3.7, optional | The `LinkPreview` component |
| `symfony/rate-limiter` | ^8.2, optional | The rate limit of the pasted links |
| `symfony/stimulus-bundle` | ^3.7, optional | The `link-preview` controller, from `assets/` |
| `phil/media-bundle` | ^0.1, optional | A medium as a page's image |
| `phil/http-cache-tags-bundle` | ^0.1, optional | The tags of the served cards |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\SocialCardsBundle\PhilSocialCardsBundle::class => ['all' => true],
];
```

## The route

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards:
    resource: '@PhilSocialCardsBundle/config/routes.php'
```

| Route | Path |
|---|---|
| `phil_social_cards_image` | `/social-cards/{fingerprint}.png`, `GET` and `HEAD`, the fingerprint 32 hexadecimal digits |

The endpoint of pasted links is imported apart, behind the firewall of the members who write:

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards_link_preview:
    resource: '@PhilSocialCardsBundle/config/link_preview_routes.php'
```

| Route | Path |
|---|---|
| `phil_social_cards_link_preview` | `/social-cards/link-preview?url=…`, `GET` |

## The Stimulus controller

The `link-preview` controller is in `assets/`, declared in its `package.json`, for Symfony UX: with AssetMapper,
`importmap:require` or the StimulusBundle's `controllers.json` enables it as
`phil--social-cards-bundle--link-preview`. It needs `@hotwired/stimulus` and nothing else.
