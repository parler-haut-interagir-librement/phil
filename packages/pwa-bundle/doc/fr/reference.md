<!-- phil-dev source-sha256=6f2fd0c996693d69e134841686c43a5c291215547c9e32f7264dcb4967a7d038 -->
# Référence

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Manifest\DisplayMode` | Les modes d'affichage ; `isFallback()` pour les quatre de `display`. |
| `Domain\Manifest\Orientation`, `IconPurpose`, `CacheStrategy` | Les orientations, les usages d'une icône, les stratégies du worker. |
| `Domain\ValueObject\Manifest` | Le manifeste, vérifié (service public). |
| `Domain\ValueObject\Icon`, `Shortcut` | Une icône, un raccourci. |
| `Domain\ValueObject\WorkerSettings`, `CacheRule` | Les réglages du worker, une règle par préfixe. |
| `Domain\ValueObject\Rule` | Les vérifications des textes, des chemins, des chemins de ressources et des couleurs. |
| `Domain\Exception\PwaException`, `InvalidManifest` | L'interface des exceptions du bundle ; un membre qui ne peut pas être. |

## Application

| Type | Rôle |
|---|---|
| `Application\Contract\AssetUrls` | L'adresse d'une ressource. |
| `Application\Contract\PrecacheProvider` | Les chemins ajoutés à la liste de préchargement. |
| `Application\ManifestReader` | Lit le manifeste et les réglages du worker à partir de données simples. |
| `Application\ManifestDocument` | Le JSON du manifeste (service public). |
| `Application\ServiceWorkerScript` | Les réglages du worker, leur version, et le script de `/sw.js` (service public). |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\ConfiguredPwa` | Le manifeste et les réglages de la configuration. |
| `Infrastructure\Asset\PackagesAssetUrls`, `PlainAssetUrls` | Les adresses du composant Asset, ou des chemins logiques. |
| `Infrastructure\Http\PwaController` | Le manifeste, le worker et la page hors ligne. |
| `Infrastructure\Twig\PwaExtension` | Les fonctions Twig. |
| `Infrastructure\DependencyInjection\PwaServices`, `AssetUrlsPass` | Le câblage. |

## Routes

| Route | Chemin |
|---|---|
| `phil_pwa_manifest` | `/manifest.webmanifest` |
| `phil_pwa_service_worker` | `/sw.js` |
| `phil_pwa_offline` | `/offline` |

## Twig

| Fonction | Donne |
|---|---|
| `phil_pwa_head()` | `<link rel="manifest">` et `<meta name="theme-color">`. |
| `phil_pwa_manifest_path()` | Le chemin du manifeste. |
| `phil_pwa_service_worker_path()` | Le chemin du worker. |
| `phil_pwa_scope()` | La portée du worker. |

## Ressources

`@phil/pwa-bundle/service_worker.js`, `@phil/pwa-bundle/pwa_controller.js` (`phil--pwa-bundle--pwa`),
`@phil/pwa-bundle/presentation_controller.js` (`phil--pwa-bundle--presentation`). Les gabarits
`@PhilPwa/layout.html.twig` et `@PhilPwa/offline.html.twig` ; le domaine de traduction `phil_pwa` en anglais et en
français.
