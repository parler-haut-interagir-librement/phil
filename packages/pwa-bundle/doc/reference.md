# Reference

## Domain

| Type | Role |
|---|---|
| `Domain\Manifest\DisplayMode` | The display modes; `isFallback()` for the four of `display`. |
| `Domain\Manifest\Orientation`, `IconPurpose`, `CacheStrategy` | Orientations, purposes of an icon, strategies of the worker. |
| `Domain\ValueObject\Manifest` | The manifest, checked (public service). |
| `Domain\ValueObject\Icon`, `Shortcut` | An icon, a shortcut. |
| `Domain\ValueObject\WorkerSettings`, `CacheRule` | The settings of the worker, a rule by prefix. |
| `Domain\ValueObject\Rule` | The checks of texts, paths, asset paths and colours. |
| `Domain\Exception\PwaException`, `InvalidManifest` | The interface of the bundle's exceptions; a member that cannot be. |

## Application

| Type | Role |
|---|---|
| `Application\Contract\AssetUrls` | The address of an asset. |
| `Application\Contract\PrecacheProvider` | Paths added to the precache list. |
| `Application\ManifestReader` | Reads the manifest and the settings of the worker from plain data. |
| `Application\ManifestDocument` | The JSON of the manifest (public service). |
| `Application\ServiceWorkerScript` | The settings of the worker, their version, and the script of `/sw.js` (public service). |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\ConfiguredPwa` | The manifest and the settings of the configuration. |
| `Infrastructure\Asset\PackagesAssetUrls`, `PlainAssetUrls` | Addresses from the Asset component, or from logical paths. |
| `Infrastructure\Http\PwaController` | The manifest, the worker and the offline page. |
| `Infrastructure\Twig\PwaExtension` | The Twig functions. |
| `Infrastructure\DependencyInjection\PwaServices`, `AssetUrlsPass` | The wiring. |

## Routes

| Route | Path |
|---|---|
| `phil_pwa_manifest` | `/manifest.webmanifest` |
| `phil_pwa_service_worker` | `/sw.js` |
| `phil_pwa_offline` | `/offline` |

## Twig

| Function | Gives |
|---|---|
| `phil_pwa_head()` | `<link rel="manifest">` and `<meta name="theme-color">`. |
| `phil_pwa_manifest_path()` | The path of the manifest. |
| `phil_pwa_service_worker_path()` | The path of the worker. |
| `phil_pwa_scope()` | The scope of the worker. |

## Assets

`@phil/pwa-bundle/service_worker.js`, `@phil/pwa-bundle/pwa_controller.js` (`phil--pwa-bundle--pwa`),
`@phil/pwa-bundle/presentation_controller.js` (`phil--pwa-bundle--presentation`). Templates
`@PhilPwa/layout.html.twig` and `@PhilPwa/offline.html.twig`; the translation domain `phil_pwa` in English and French.
