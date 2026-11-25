# Changelog

All notable changes to `phil/video-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- Requires the latest minor of each dependency (`symfony/service-contracts` 3.8), and of each development requirement
  (`twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `twig/extra-bundle` 3.31, `twig/html-extra` 3.31).

## 0.1.0-alpha - 2026-11-15

### Added

- The videos: `HostedVideo` with its `VideoSource`s, an HLS stream and its `TextTrack`s; `LinkedVideo` of YouTube or
  Vimeo, read from the address an author pastes, its player without cookies from phil/url; a poster, a transcript and
  chapters from phil/timed-text for both.
- The player `PhilVideo:Player`: a hosted video drawn with the recipe Video of the kit, no autoplay, nothing fetched
  before the viewer asks; a linked video behind a facade of the site's image, a button and a link to the platform;
  chapters and transcript beside, links to their instants for a hosted video.
- The Stimulus controller `player` and its stylesheet: the platform's player loaded only when pressed, in a sandboxed
  frame; the HLS stream where the browser plays it; seeking; the current chapter and cue marked; the parts watched sent
  only when the page says the viewer agreed.
- Viewing progress with consent: `ViewingProgress`, `Heatmap`, `ViewingTracker`, the ports `ViewingConsent` and
  `ViewingStore`, the event `VideoProgressed`, `NoViewingConsent`, `InMemoryViewingStore` and `CacheViewingStore`.
- `ProgressController` and its route: a kilobyte from a page of the site, `204` whether counted or not, never cached.
- `TextTrackResponse`: a `Track` served as WebVTT.
- The configuration `phil_video`: tracking, its endpoint, consent and store, and the subdirectory of the recipes of the
  kit.
- The texts of the player in English, French, Spanish, German, Italian and Portuguese.
- The fuzz targets `linked-url` and `progress`.
- The package skeleton, its documentation in `doc/`, `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
