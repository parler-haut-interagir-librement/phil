# Reference

| Type | Role |
|---|---|
| `PhilVideoBundle` | The bundle and its configuration `phil_video` |
| `Application\Video` | What every video shows: identifier, title, size, poster, transcript, chapters |
| `Application\HostedVideo` | Files, an HLS stream and WebVTT tracks the site or a service serves |
| `Application\LinkedVideo` | A video of YouTube or Vimeo behind a facade, its player without cookies |
| `Application\VideoSource` | A file of a hosted video: address and type |
| `Application\TextTrack` | A WebVTT file: kind, language, label, default |
| `Application\TextTrackKind` | Captions, subtitles, descriptions, chapters |
| `Application\ViewingProgress` | The hundred parts of one viewing; the share watched |
| `Application\Heatmap` | The counts of each part, and of the viewings |
| `Application\ViewingTracker` | Counts a viewing with consent, and says it |
| `Application\NoViewingConsent` | The default consent: nobody agreed |
| `Application\Contract\ViewingConsent` | Whether the current viewer agreed |
| `Application\Contract\ViewingStore` | Where the heat maps are kept |
| `Application\Event\VideoProgressed` | A viewing counted |
| `Application\Exception\InvalidVideo` | A video, a source, a track or a viewing refused |
| `Infrastructure\Http\ProgressController` | The route of the viewings |
| `Infrastructure\Http\TextTrackResponse` | A `Track` served as WebVTT |
| `Infrastructure\Memory\InMemoryViewingStore` | The heat maps of one request |
| `Infrastructure\Cache\CacheViewingStore` | The heat maps in a PSR-6 pool |
| `Infrastructure\Twig\PlayerComponent` | `PhilVideo:Player` |
| `Infrastructure\DependencyInjection\VideoServices` | The services, from the configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | The subdirectory of the recipes of the kit |
| `Infrastructure\Memory\Counts` | Internal: a heat map with one viewing more |
