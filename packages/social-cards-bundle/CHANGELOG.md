# Changelog

All notable changes to `phil/social-cards-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Changed

- Requires the latest minor of each dependency (`twig/twig` 3.31, `phil/safe-http` 0.2, `symfony/cache-contracts` 3.8,
  `symfony/service-contracts` 3.8), and of each development requirement (`symfony/ux-twig-component` 3.8,
  `symfony/http-client-contracts` 3.8).

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.1-alpha - 2026-11-20

The cards can be drawn by ImageMagick, a message keeps the link preview its author
chose, and `phil/media-bundle` 0.2 and `phil/safe-http` 0.2 are accepted. A patch version and not a minor one: the
configuration of 0.1.0 keeps its meaning, and the floor of `phil/social-cards` moves within 0.1.

### Added

- `cards.renderer`: `gd`, the default, or `imagick`, the cards drawn by `ImagickCardRenderer` of phil/social-cards —
  the text of Arabic, Hebrew and the scripts of India shaped when ImageMagick is built with libraqm —, refused when the
  container is built without the imagick extension; the service `Phil\SocialCards\Contract\CardRenderer`, an alias of
  the renderer picked, which `CardGenerator` takes. `ext-imagick` is suggested.
- `LinkPreviews::forMessage()`: the preview a message keeps from its text and the link its author chose — the first
  link when no choice is sent, none when the preview was removed, and only a link the text holds.

### Changed

- `phil/media-bundle` 0.2 and `phil/safe-http` 0.2 are accepted beside 0.1.
- The `link-preview` Stimulus controller enables its `field` target when it connects: rendered `disabled`, the field
  is sent only with the script, so that a form sent without it keeps the preview of the first link.
- It requires `phil/social-cards` 0.1.1 or later, whose `ImagickCardRenderer` the renderer `imagick` uses.

## 0.1.0-alpha - 2026-11-12

### Added

- `PhilSocialCardsBundle` and the `phil_social_cards` configuration: `site_name`, `locale`, `twitter_site`,
  `fediverse_creator`, `default_image`, `cards` (`enabled`, `directory`, `cache_max_age`, `async`,
  `default_template`, `templates`) and `preview` (`enabled`, `timeout`, `max_duration`, `max_bytes`, `max_redirects`,
  `cache_ttl`, `user_agent` on one line, `limiter`); locales, handles, the default image and the shape of each
  template checked when the container is built.
- `SharePage`, the values the request sets, reset between requests; `ShareMetadataSource`, autoconfigured, the values
  of the application's models; `ShareMetadataFactory`, the four layers of a page's metadata, its address and locale
  from the request, a medium of phil/media-bundle as its image, else its card.
- `social_meta()` and `social_page()`, in `SocialMetaExtension`.
- `CardTemplates`, read once per process; `CardRequest` and `CardRequestStore`, what a card is drawn from, kept as JSON
  by `DirectoryCardRequestStore`; `SocialCards`, the fingerprint of a page's card, queued the first time, drawn by a
  worker or by the first request for it.
- `SocialCardController`: `/social-cards/{fingerprint}.png`, in `config/routes.php`, `public` and `immutable` for
  `cache_max_age`, with the fingerprint as `ETag` and a `304`; tagged `social-card` and `social-card:<fingerprint>`
  with phil/http-cache-tags-bundle.
- `CardQueue`, `MessengerCardQueue`, `GenerateSocialCard` — the fingerprint alone — and `GenerateSocialCardHandler`,
  with Messenger.
- `PageFetcher` and `GuardedPageFetcher`: the head of a page through phil/safe-http — addresses of host names only,
  `NoPrivateNetworkHttpClient` at the first request and at each redirect, bounded redirects, time and bytes —, only a
  `200` answer of HTML, read up to the end of its head; the client is the service `phil_social_cards.safe_http_client`.
  `LinkPreviews`, the previews kept in `cache.app`, a dead link kept as nothing; `firstIn()`, the preview of the first
  link of a text.
- The `PhilSocialCards:LinkPreview` component, its image lazy and without a referrer; `snapshot`, a preview kept with a
  message, shown without fetching; no link for an address that cannot be fetched.
- `LinkPreviewController`: `/social-cards/link-preview`, in `config/link_preview_routes.php`, the preview of a pasted
  link as JSON, `204`, `400` or `429` behind the rate limiter of `preview.limiter`, `private`.
- The `link-preview` Stimulus controller, in `assets/`: the preview of the first link of a text, built from text nodes,
  its address in a hidden field, removable by its author.
- `phil:social-cards:check`: a path of the application rendered, or an address fetched through the guarded fetcher,
  its tags listed and checked, a failure on an error.
- `RequiredServicesPass`: the queue without a message bus, the images of media without their extension, left out.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
