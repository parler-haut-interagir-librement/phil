<!-- phil-dev source-sha256=55516b21c6c7c8380316d55ec076156b648f0cd3df5ed8bb34418bff741cd897 -->
# Vie privée et accessibilité

## Ce qui quitte le serveur

Rien : la bibliothèque exécute ffmpeg et ffprobe sur le serveur, sur des fichiers locaux, et n'appelle aucun service. Un
service de transcodage derrière `Contract\Transcoder` est un adaptateur de l'application, dont les propres conditions
s'appliquent.

## Ce que ffmpeg peut atteindre

Chaque exécution est fermée au réseau et à l'interpréteur de commandes :

- un fichier est nommé `file:<path>`, et on dit à ffmpeg de n'ouvrir que le protocole `file`
  (`-protocol_whitelist file`) : une liste de lecture ou un fichier de concaténation qui nomme une adresse ne peut pas
  faire récupérer cette adresse par le serveur ;
- aucune entrée standard n'est lue (`-nostdin`) ;
- les arguments forment une liste, chacun passé tel quel au programme : un nom de fichier n'est jamais lu comme une
  commande ;
- l'entrée, la sortie, la durée, la sortie affichée et le temps sont bornés par `FfmpegLimits`.

Les échantillons temporaires d'une forme d'onde ont un nom aléatoire et sont supprimés quoi qu'il arrive.

## Les étiquettes d'un fichier

Un enregistreur écrit des étiquettes dans un fichier : un titre, un auteur, parfois un lieu ou l'appareil. `read()` les
donne à l'application, qui décide de ce qu'elle montre ; une version les supprime toutes, de sorte que les fichiers
qu'un site sert ne portent rien de ce que portait le fichier téléversé.

## Ce qu'un lecteur doit à ses auditeurs

La bibliothèque prépare ce dont un lecteur accessible a besoin ; `phil/audio-bundle` le dessine :

- une transcription pour chaque piste qui contient de la parole, dans sa langue, afin que l'auditeur qui ne l'entend pas
  la lise (WCAG 1.2.1) ;
- des chapitres avec leurs titres, une table des matières qu'atteignent un lecteur d'écran et le clavier ;
- une forme d'onde réduite à quelques centaines de barres, dessinée comme décoration à côté des commandes du
  navigateur, jamais à leur place.
