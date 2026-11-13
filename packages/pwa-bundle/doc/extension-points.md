# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add paths to the precache list | Implement `Application\Contract\PrecacheProvider`; autoconfiguration tags it `phil_pwa.precache_provider` ([Integrations](integrations.md)). |
| Give the addresses of assets otherwise — a CDN, another versioning | Alias `Application\Contract\AssetUrls` to a service of the application. |
| Write the worker otherwise | Set `service_worker.script` to an asset of the application; it reads `self.philPwa`, as the bundle's does. |
| Change the offline page | Override `@PhilPwa/offline.html.twig`, set `layout`, or add `translations/phil_pwa.<locale>.php`. |
| Build the manifest or the script elsewhere | Use the public services `Domain\ValueObject\Manifest`, `Application\ManifestDocument` and `Application\ServiceWorkerScript`. |
| React in the page | Listen to the events of the controllers ([JavaScript](javascript.md)). |

`AssetUrls` answers the public address of a logical path; without the Asset component, `PlainAssetUrls` puts the path
under `/`. None of the services keeps state between two requests.
