<!-- phil-dev source-sha256=d61910d2fc690b0b22830cc8c98901e3ad430deca20870075d1887835f9dcdcb -->
# Points d'extension

## Ports

| Port | Valeur par défaut | Une application le remplace pour |
|---|---|---|
| `Phil\AudioBundle\Application\Contract\AudioFiles` | `MediaLibraryAudioFiles` avec phil/media-bundle | Donner aux tâches des copies locales tirées de son propre stockage : `storage.files` |
| `Phil\AudioBundle\Application\Contract\RenditionStore` | `MediaStorageRenditions` avec phil/media-bundle | Conserver les versions et les formes d'onde ailleurs : `storage.renditions` |
| `Phil\Audio\Contract\Transcoder`, `MetadataReader`, `PeaksGenerator` | `phil_audio.tools`, selon `tools` | Employer un autre outil : décorer ou remplacer `phil_audio.tools` |

Chacun ne garde aucun état entre deux messages, de sorte qu'un worker exécute une tâche après l'autre.

## Services

| Service | Rôle |
|---|---|
| `Phil\AudioBundle\Application\AudioJobs` | Planifie les tâches d'un téléversement |
| `Phil\AudioBundle\Application\Renditions` | Les versions de la configuration, par nom |
| `phil_audio.listening_policy`, aliasé `Phil\Audio\ListeningPolicy` | La politique par défaut d'un créateur qui n'a pas choisi |
| `Phil\AudioBundle\Infrastructure\Twig\PlayerComponent` | `PhilAudio:Player` |

## Gabarits et contrôleur

`@PhilAudio/components/Player.html.twig` se remplace comme tout gabarit d'un bundle. Le contrôleur Stimulus ne distribue
aucun événement ; un autre contrôleur lit `aria-current` ou écoute les propres événements de l'élément audio.

## Stabilité

La configuration, les ports, les messages et les propriétés du composant sont l'API publique, en 0.1 : ils peuvent
changer dans une version mineure jusqu'à la 1.0.
