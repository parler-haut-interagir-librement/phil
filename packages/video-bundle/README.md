# phil/video-bundle

**Videos in Symfony: hosted files or an HLS stream played by the browser's video element, YouTube and Vimeo behind a
facade that loads nothing before the viewer asks, WebVTT captions, chapters and a transcript beside the video, and the
share watched and an aggregate heat map counted only with consent.**

A hosted video — files the site serves, or a stream a connected service serves for it — is the recipe Video of the PHIL
UX kit: the browser's own player, its poster, its WebVTT tracks, captions first, nothing played before the viewer asks.
Where the browser plays HLS, the player switches to the stream. Nothing is transcoded here.

A linked video of YouTube or Vimeo is a facade: the image the site serves and a button. Nothing of the platform is
fetched before the button is pressed; the platform's player then loads from its address that writes no cookies,
`youtube-nocookie.com`, or Vimeo with "do not track". Without JavaScript, a link leads to the platform.

Both show their chapters and their transcript beside the video, from phil/timed-text. The viewing of a hosted video —
which hundredths were watched — is sent only when tracking is on and the application's consent manager says the viewer
agreed; the bundle adds it to an aggregate heat map and dispatches `VideoProgressed`, which a course listens to.

## Status

| Part | State |
|---|---|
| `HostedVideo`, `LinkedVideo`, `VideoSource`, `TextTrack`: files, HLS, WebVTT tracks, YouTube and Vimeo without cookies | Implemented and tested |
| `PlayerComponent` and its template: the recipe Video, the facade, chapters and transcript beside | Implemented and tested in a booted kernel |
| The Stimulus controller `player`: the facade loaded on demand, HLS, seeking, viewing sent only with consent | Implemented and tested under Node |
| `ViewingProgress`, `Heatmap`, `ViewingTracker`, `VideoProgressed`: the share watched and the heat map, with consent | Implemented and tested |
| `ProgressController` and its route, `InMemoryViewingStore`, `CacheViewingStore` | Implemented, tested and fuzzed |
| `TextTrackResponse`: a `Track` served as WebVTT | Implemented and tested |
| Transcoding uploads | Not done here: a service, or an adapter of the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the recipes of the kit, the controller, the route. |
| [Configuration](doc/configuration.md) | Every key of `phil_video`. |
| [The player](doc/player.md) | Hosted and linked videos, the component, the facade, tracks, chapters and transcript. |
| [Viewing progress](doc/tracking.md) | The share watched, the heat map, consent, the event and the stores. |
| [Privacy and accessibility](doc/privacy-and-accessibility.md) | What reaches a platform, what is counted, what the player owes its viewers. |
| [Extension points](doc/extension-points.md) | The ports, the event and the templates. |
| [Testing](doc/testing.md) | The tests, the fuzz targets, the controller under Node. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Phil\Url\Url;
use Phil\VideoBundle\Application\HostedVideo;
use Phil\VideoBundle\Application\LinkedVideo;
use Phil\VideoBundle\Application\TextTrack;
use Phil\VideoBundle\Application\VideoSource;

$talk = new HostedVideo('talk-12', 'The talk', [new VideoSource(Url::fromString('https://cdn.example.com/talk.mp4'))],
    poster: Url::fromString('https://cdn.example.com/talk.jpg'),
    tracks: [new TextTrack(Url::fromString('https://cdn.example.com/talk.fr.vtt'), 'fr-CA', 'Français', default: true)]);

$trailer = LinkedVideo::fromUrl('trailer', 'The trailer', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', Url::fromString('https://cdn.example.com/trailer.jpg'));
```

```twig
<twig:PhilVideo:Player :video="talk" id="talk-12" />
<twig:PhilVideo:Player :video="trailer" id="trailer" />
```

## Requirements

PHP 8.5 or later; Symfony 8.2 or later; Symfony UX Twig Component 3.7 or later, and the recipes Video, Image and Button
of the kit with the bundle of phil/ux-kit, for the player.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the [code of
conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md) explains, never in
a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
