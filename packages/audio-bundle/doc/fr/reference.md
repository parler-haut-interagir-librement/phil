<!-- phil-dev source-sha256=f00eb87bd88a77d89a935bd63a58e7299665e1114293614d0c9f8a6bd4a9dbb4 -->
# Référence

| Type | Rôle |
|---|---|
| `PhilAudioBundle` | Le bundle et sa configuration `phil_audio` |
| `Application\AudioJobs` | Planifie les versions, les versions de l'extrait et la forme d'onde d'un téléversement |
| `Application\Renditions` | Les versions de la configuration, par nom |
| `Application\Message\TranscodeAudio` | La tâche d'une version : identifiant, version, extrait |
| `Application\Message\ComputeWaveform` | La tâche de la forme d'onde : identifiant |
| `Application\Handler\TranscodeAudioHandler` | Produit une version et supprime ses fichiers temporaires |
| `Application\Handler\ComputeWaveformHandler` | Calcule et conserve la forme d'onde |
| `Application\Contract\AudioFiles` | Les copies locales des téléversements |
| `Application\Contract\RenditionStore` | Où les versions et les formes d'onde sont conservées |
| `Application\Exception\AudioNotFound` | Aucun téléversement n'a cet identifiant |
| `Application\Exception\UnknownRendition` | Aucune version n'a ce nom |
| `Infrastructure\MediaBundle\MediaLibraryAudioFiles` | Les copies locales tirées de la bibliothèque de phil/media-bundle, lues en flux |
| `Infrastructure\MediaBundle\MediaStorageRenditions` | Les versions et les formes d'onde dans le stockage de phil/media-bundle |
| `Infrastructure\Twig\PlayerComponent` | `PhilAudio:Player` |
| `Infrastructure\DependencyInjection\AudioServices` | Les services, selon la configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Le sous-répertoire des recettes du kit |
| `Infrastructure\DependencyInjection\JobsPass` | Retire les tâches sans bus de messages |
