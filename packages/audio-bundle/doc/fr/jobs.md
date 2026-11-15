<!-- phil-dev source-sha256=8bfb15afc4436d40c245f493c9743cbedaf6c716eeb07cc8e4256880dcb9ab37 -->
# Les tâches

## À partir d'un téléversement

Un fichier audio est téléversé par phil/media-bundle, ou conservé par l'application. Ensuite :

```php
use Phil\Audio\Excerpt;
use Phil\AudioBundle\Application\AudioJobs;

$audioJobs->schedule($mediaId);                                // each rendition, the waveform
$audioJobs->schedule($mediaId, new Excerpt(30_000, 30_000));   // and each rendition of the excerpt
```

`schedule()` distribue, sur le bus par défaut :

| Message | Ce qu'il porte | Son gestionnaire |
|---|---|---|
| `TranscodeAudio` | l'identifiant, le nom de la version, et le début et la durée de l'extrait, en millisecondes, ou aucun | demande une copie locale à `AudioFiles`, la transcode dans un fichier temporaire, le donne à `RenditionStore::save()`, supprime les deux |
| `ComputeWaveform` | l'identifiant | demande une copie locale, calcule les crêtes, les donne à `RenditionStore::savePeaks()`, libère la copie |

Les messages portent des identifiants et des instants : aucun audio, aucun nom, rien de la personne qui l'a téléversé. Un
worker récupère lui-même le fichier, de sorte qu'un message reste de quelques octets sur n'importe quel transport.

Avec `tools: null`, `schedule()` ne distribue rien. Sans bus de messages, `AudioJobs` et les gestionnaires sont retirés.

## Échecs

| Ce qui arrive | La tâche |
|---|---|
| Le fichier a disparu, la version n'est plus configurée, l'extrait est invalide | Échoue définitivement : `UnrecoverableMessageHandlingException` |
| L'outil refuse le fichier, ou une limite est dépassée | Échoue définitivement |
| ffmpeg est introuvable ou inexécutable | Échoue comme toute erreur, selon la stratégie de nouvelle tentative du transport, jusqu'à son installation |

Quoi qu'il arrive, la copie locale est libérée et la version temporaire supprimée.

## Avec phil/media-bundle

`MediaLibraryAudioFiles` copie l'original d'un média dans un fichier temporaire nommé `phil-audio-source-<random>`,
jamais d'après le nom que la personne a choisi au téléversement, dans `temporary_directory`. Le fichier est créé pour
cette copie seule — un nom qui existe déjà, fichier ou lien, est refusé — et seul son propriétaire peut le lire. Une
copie qui échoue est supprimée avant que l'erreur se propage, et `release()` ne supprime que les copies du bundle.

L'original est lu en flux dans le fichier, par morceaux, par `MediaLibrary::readOriginal()`, que phil/media-bundle a
depuis la 0.1.1 (les versions plus anciennes sont en conflit avec le bundle) : avec un stockage qui lit en flux, comme
les stockages de système de fichiers et Flysystem de phil/media-bundle, la mémoire du worker ne croît pas avec la durée
de l'enregistrement. Un original absent du stockage est un fichier disparu : la tâche échoue définitivement. Un stockage
qui lève une exception, ou un disque qui refuse la copie, est une erreur ordinaire, reprise selon la stratégie de
nouvelle tentative du transport. Le service ne garde rien entre deux tâches : un worker de longue durée le réutilise tel
quel.

`MediaStorageRenditions` écrit, sous `<prefix>/<medium>/` : `<rendition>.<ext>`, `<rendition>-excerpt.<ext>` et
`peaks.json`. Un identifiant ou un nom de version ne contient que des lettres, des chiffres, `_` et `-`, de sorte
qu'aucune clé ne sort de son dossier. `peaks($mediaId)` relit la forme d'onde pour le lecteur.

## Servir les versions

Les versions sont des fichiers du stockage. L'application les sert — un contrôleur, une adresse signée de son
stockage — et ne sert les fichiers entiers qu'aux auditeurs qui y ont droit : la politique d'écoute décide de ce que
la page offre, pas de ce que renvoie une adresse devinée.
