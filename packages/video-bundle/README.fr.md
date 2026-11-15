<!-- phil-dev source-sha256=16985b0d8a6fe37bd70b0f268b4b4cc0a7090a011bcd093ec1c89fc24175923a -->
# phil/video-bundle

**Les vidéos dans Symfony : fichiers hébergés ou flux HLS joués par l'élément vidéo du navigateur, YouTube et Vimeo
derrière une façade qui ne charge rien avant que le spectateur le demande, sous-titres WebVTT, chapitres et transcription
à côté de la vidéo, et la part regardée et une carte de chaleur agrégée comptées seulement avec consentement.**

Une vidéo hébergée — des fichiers que le site sert, ou un flux qu'un service connecté sert pour lui — est la recette
Video du kit PHIL UX : le lecteur propre au navigateur, son affiche, ses pistes WebVTT, les sous-titres pour malentendants
en premier, rien de joué avant que le spectateur le demande. Là où le navigateur lit le HLS, le lecteur passe au flux.
Rien n'est transcodé ici.

Une vidéo liée de YouTube ou de Vimeo est une façade : l'image que le site sert et un bouton. Rien de la plateforme n'est
récupéré avant l'appui sur le bouton ; le lecteur de la plateforme se charge alors depuis son adresse qui n'écrit aucun
témoin, `youtube-nocookie.com`, ou Vimeo avec « ne pas suivre ». Sans JavaScript, un lien mène à la plateforme.

Les deux montrent leurs chapitres et leur transcription à côté de la vidéo, tirés de phil/timed-text. Le visionnement
d'une vidéo hébergée — quels centièmes ont été regardés — n'est envoyé que si le suivi est activé et que le gestionnaire
de consentement de l'application dit que le spectateur a accepté ; le bundle l'ajoute à une carte de chaleur agrégée et
distribue `VideoProgressed`, qu'un cours écoute.

## État

| Partie | État |
|---|---|
| `HostedVideo`, `LinkedVideo`, `VideoSource`, `TextTrack` : fichiers, HLS, pistes WebVTT, YouTube et Vimeo sans témoin | Implémentée et testée |
| `PlayerComponent` et son gabarit : la recette Video, la façade, chapitres et transcription à côté | Implémentée et testée dans un noyau démarré |
| Le contrôleur Stimulus `player` : la façade chargée à la demande, HLS, positionnement, visionnement envoyé seulement avec consentement | Implémentée et testée sous Node |
| `ViewingProgress`, `Heatmap`, `ViewingTracker`, `VideoProgressed` : la part regardée et la carte de chaleur, avec consentement | Implémentée et testée |
| `ProgressController` et sa route, `InMemoryViewingStore`, `CacheViewingStore` | Implémentée, testée et soumise au fuzzing |
| `TextTrackResponse` : une `Track` servie en WebVTT | Implémentée et testée |
| Le transcodage des téléversements | Pas fait ici : un service, ou un adaptateur de l'application |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, les recettes du kit, le contrôleur, la route. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_video`. |
| [Le lecteur](doc/fr/player.md) | Les vidéos hébergées et liées, le composant, la façade, les pistes, les chapitres et la transcription. |
| [Progression du visionnement](doc/fr/tracking.md) | La part regardée, la carte de chaleur, le consentement, l'événement et les stockages. |
| [Vie privée et accessibilité](doc/fr/privacy-and-accessibility.md) | Ce qui atteint une plateforme, ce qui est compté, ce que le lecteur doit à ses spectateurs. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, l'événement et les gabarits. |
| [Tests](doc/fr/testing.md) | Les tests, les cibles de fuzzing, le contrôleur sous Node. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

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

## Prérequis

PHP 8.5 ou une version plus récente ; Symfony 8.2 ou une version plus récente ; Symfony UX Twig Component 3.7 ou une
version plus récente, et les recettes Video, Image et Button du kit avec le bundle de phil/ux-kit, pour le lecteur.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
