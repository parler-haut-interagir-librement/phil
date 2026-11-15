<!-- phil-dev source-sha256=f904b4bb7d64dc5d283f45e216290d98db45e2fbaa78dc6ba06937486abfbfed -->
# Référence

| Type | Rôle |
|---|---|
| `PhilVideoBundle` | Le bundle et sa configuration `phil_video` |
| `Application\Video` | Ce que montre toute vidéo : identifiant, titre, taille, affiche, transcription, chapitres |
| `Application\HostedVideo` | Des fichiers, un flux HLS et des pistes WebVTT que le site ou un service sert |
| `Application\LinkedVideo` | Une vidéo de YouTube ou de Vimeo derrière une façade, son lecteur sans témoin |
| `Application\VideoSource` | Un fichier d'une vidéo hébergée : adresse et type |
| `Application\TextTrack` | Un fichier WebVTT : genre, langue, libellé, par défaut |
| `Application\TextTrackKind` | Sous-titres pour malentendants, sous-titres, descriptions, chapitres |
| `Application\ViewingProgress` | Les cent parties d'un visionnement ; la part regardée |
| `Application\Heatmap` | Les compteurs de chaque partie, et des visionnements |
| `Application\ViewingTracker` | Compte un visionnement avec consentement, et le dit |
| `Application\NoViewingConsent` | Le consentement par défaut : personne n'a accepté |
| `Application\Contract\ViewingConsent` | Si le spectateur courant a accepté |
| `Application\Contract\ViewingStore` | Où les cartes de chaleur sont conservées |
| `Application\Event\VideoProgressed` | Un visionnement compté |
| `Application\Exception\InvalidVideo` | Une vidéo, une source, une piste ou un visionnement refusé |
| `Infrastructure\Http\ProgressController` | La route des visionnements |
| `Infrastructure\Http\TextTrackResponse` | Une `Track` servie en WebVTT |
| `Infrastructure\Memory\InMemoryViewingStore` | Les cartes de chaleur d'une requête |
| `Infrastructure\Cache\CacheViewingStore` | Les cartes de chaleur dans un pool PSR-6 |
| `Infrastructure\Twig\PlayerComponent` | `PhilVideo:Player` |
| `Infrastructure\DependencyInjection\VideoServices` | Les services, selon la configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Le sous-répertoire des recettes du kit |
| `Infrastructure\Memory\Counts` | Interne : une carte de chaleur avec un visionnement de plus |
