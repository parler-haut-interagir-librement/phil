<!-- phil-dev source-sha256=6d9d854bfd6d6d23b8e7ecbab109e2c2dc14f4afd5801cc4d01421811ead6f4b -->
# phil/audio-bundle

**phil/audio dans Symfony : ffmpeg ou aucun outil selon la configuration, versions et formes d'onde produites par des
tâches Messenger qui ne portent que des identifiants, téléversements par phil/media-bundle, politique d'écoute par défaut
du créateur, et un lecteur qui reste un `<audio>` du navigateur sans JavaScript.**

Le bundle configure les outils de phil/audio — `null`, qui sert les fichiers tels que téléversés, ou `ffmpeg` —, les
versions produites à partir de chaque téléversement, et la forme d'onde. `AudioJobs::schedule()` distribue un message par
version, par version de l'extrait quand le créateur en offre un, et un pour la forme d'onde ; chaque message porte
l'identifiant du fichier et rien d'autre, et son gestionnaire récupère une copie locale, exécute l'outil, conserve le
résultat et supprime ses fichiers temporaires. Avec phil/media-bundle installé, les téléversements viennent de sa
bibliothèque et les résultats vont dans son stockage.

`<twig:PhilAudio:Player :audio="track" :entitled="bought" />` dessine une piste : le `<audio>` du navigateur, avec ses
propres commandes, les fichiers que cet auditeur peut entendre, la forme d'onde, les chapitres et les transcriptions,
dont les liens ouvrent l'instant dans le fichier sans JavaScript. Un contrôleur Stimulus léger dessine la forme d'onde en
SVG, positionne l'audio depuis un chapitre ou un repère, et marque le courant. Les liens sont la recette Button du kit
PHIL UX — ou du kit Shadcn — installée par le Symfony UX Toolkit.

## État

| Partie | État |
|---|---|
| La configuration `phil_audio` : outils, ffmpeg et ses limites, versions, forme d'onde, politique d'écoute par défaut, stockage | Implémentée et testée |
| `AudioJobs`, `TranscodeAudio`, `ComputeWaveform` et leurs gestionnaires : tâches d'identifiants, fichiers temporaires supprimés | Implémentée et testée |
| `MediaLibraryAudioFiles`, `MediaStorageRenditions` : téléversements et résultats par phil/media-bundle, l'original lu en flux vers la copie locale quand phil/media-bundle le permet | Implémentée et testée |
| La recette Flex `recipe/0.1` : le bundle et sa configuration | Prête |
| `PlayerComponent` et son gabarit : l'élément audio, l'extrait ou rien avant un achat, chapitres et transcriptions | Implémentée et testée dans un noyau démarré |
| Le contrôleur Stimulus `player` : forme d'onde en SVG, positionnement, chapitre et repère courants | Implémentée et testée sous Node |
| Servir les versions aux seuls auditeurs qui y ont droit | À l'application : une adresse signée ou un contrôleur à elle |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, sa recette Flex, les recettes du kit, le contrôleur. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_audio`. |
| [Les tâches](doc/fr/jobs.md) | Téléversements, versions, extraits et formes d'onde par Messenger. |
| [Le lecteur](doc/fr/player.md) | Le composant, ses propriétés, ce qu'il dessine et ce qu'ajoute le contrôleur. |
| [Vie privée et accessibilité](doc/fr/privacy-and-accessibility.md) | Ce que portent les messages, ce que le lecteur doit à ses auditeurs. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports et les services qu'une application remplace. |
| [Tests](doc/fr/testing.md) | Les tests, l'application fonctionnelle, le contrôleur sous Node. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```yaml
# config/packages/phil_audio.yaml
phil_audio:
    tools: ffmpeg
    renditions:
        mp3: { format: mp3, bitrate: 128 }
        opus: { format: opus, bitrate: 64 }
```

```php
$media = $library->upload($uploadedFile->getPathname(), $uploadedFile->getClientOriginalName());
$audioJobs->schedule($media->id, new Excerpt(30_000, 30_000));
```

```twig
<twig:PhilAudio:Player :audio="track" :entitled="is_granted('LISTEN', track)" id="episode-12" />
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-sodium` ; Symfony 8.2 ou une version plus récente avec Messenger ;
Symfony UX Twig Component 3.7 ou une version plus récente et la recette Button du kit pour le lecteur ;
phil/media-bundle pour les téléversements et le stockage, ou des services de l'application.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
