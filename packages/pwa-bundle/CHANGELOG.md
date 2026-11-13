# Changelog

All notable changes to `phil/pwa-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PhilPwaBundle` and its configuration `phil_pwa`: the manifest, the service worker, the layout of the offline page
  and the cache of the manifest.
- The manifest: `Manifest`, `Icon`, `Shortcut`, `DisplayMode`, `Orientation` and `IconPurpose`, every member checked,
  paths resolved as a browser does, read by `ManifestReader`, with the fuzz target `manifest`, and written by
  `ManifestDocument` at `/manifest.webmanifest`, icons at the addresses `AssetUrls` gives.
- The service worker: `WorkerSettings`, `CacheRule` and `CacheStrategy`; `ServiceWorkerScript`, which serves at
  `/sw.js` the settings, their version and `importScripts()` of the worker served by AssetMapper; the worker of the
  bundle, with its versioned precache list, network-first navigations, cache-first and network-first rules, the
  offline page and skip waiting; responses marked `private` or `no-store` never kept.
- `PrecacheProvider`, autoconfigured, for the paths of other bundles.
- The offline page at `/offline`, in English and French, and the Twig functions `phil_pwa_head()`,
  `phil_pwa_manifest_path()`, `phil_pwa_service_worker_path()` and `phil_pwa_scope()`.
- The Stimulus controller `phil--pwa-bundle--pwa`: registering, installing, updating, the connection and the full
  screen.
- The Stimulus controller `phil--pwa-bundle--presentation`: a page on a second screen through the Presentation API, or
  a window of its own and a BroadcastChannel.
- The Flex recipe, under `recipe/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
