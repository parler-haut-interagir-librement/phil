<!-- phil-dev source-sha256=cb757066aaaff36200b714f57b7b4451cdc654d02d61003b2fb46e511acfaeb8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter des chemins à la liste de préchargement | Implémentez `Application\Contract\PrecacheProvider` ; l'autoconfiguration l'étiquette `phil_pwa.precache_provider` ([Intégrations](integrations.md)). |
| Donner autrement les adresses des ressources — un CDN, un autre versionnage | Créez un alias de `Application\Contract\AssetUrls` vers un service de l'application. |
| Écrire autrement le worker | Réglez `service_worker.script` sur une ressource de l'application ; elle lit `self.philPwa`, comme celle du bundle. |
| Changer la page hors ligne | Remplacez `@PhilPwa/offline.html.twig`, réglez `layout`, ou ajoutez `translations/phil_pwa.<locale>.php`. |
| Construire ailleurs le manifeste ou le script | Utilisez les services publics `Domain\ValueObject\Manifest`, `Application\ManifestDocument` et `Application\ServiceWorkerScript`. |
| Réagir dans la page | Écoutez les événements des contrôleurs ([JavaScript](javascript.md)). |

`AssetUrls` répond avec l'adresse publique d'un chemin logique ; sans le composant Asset, `PlainAssetUrls` place le
chemin sous `/`. Aucun des services ne garde d'état d'une requête à l'autre.
