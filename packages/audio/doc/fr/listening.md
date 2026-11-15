<!-- phil-dev source-sha256=bfffc907094979805d1a09f0df621da9cd43669423d5228dd62347a62ac0ac4b -->
# Écoute

## Une piste

`Audio` est une piste telle qu'un lecteur la montre :

| Propriété | Ce qu'elle contient |
|---|---|
| `title` | Son titre, jamais vide |
| `sources` | Ses fichiers, des `AudioSource`, dans l'ordre où un navigateur les essaie |
| `policy` | La `ListeningPolicy` du créateur ; la piste entière par défaut |
| `duration` | En millisecondes, quand elle est connue |
| `peaks` | Sa forme d'onde |
| `chapters` | Une `ChapterList` de phil/timed-text |
| `transcripts` | Des `AudioTranscript` : une `Track` de phil/timed-text, une langue BCP 47, un libellé, l'adresse de son fichier |

Une `AudioSource` est une adresse lue par phil/url, un `AudioFormat`, et l'indication que le fichier ne contient que
l'extrait.

## La politique d'écoute

Le créateur choisit ce qu'un auditeur qui n'a pas acheté une piste peut entendre :

| Politique | Avant un achat | Après |
|---|---|---|
| `ListeningPolicy::full()` | La piste entière | La piste entière |
| `ListeningPolicy::excerpt(new Excerpt(30_000, 30_000))` | L'extrait | La piste entière |
| `ListeningPolicy::purchaseOnly()` | Rien | La piste entière |

`forListener($entitled)` dit laquelle s'applique ; `$entitled` est la réponse de l'application : un achat, un abonnement,
le créateur lui-même. `Audio::playable($entitled)` donne les fichiers que le lecteur de cet auditeur peut récupérer : les
fichiers entiers, les fichiers de l'extrait, ou aucun.

La politique décide de ce que la page offre. Elle ne protège pas le fichier entier : une application qui vend des pistes
ne sert les fichiers entiers qu'aux auditeurs qui y ont droit — adresses signées, contrôleur qui vérifie — et fait de
l'extrait un fichier à part, que produit `transcode()` avec un `Excerpt`.

## Chapitres et transcriptions

Les chapitres et les transcriptions sont ceux de phil/timed-text : lus depuis WebVTT, SRT, JSON de Podcasting 2.0 ou
HTML, réécrits dans n'importe lequel d'entre eux, décalés et coupés en millisecondes entières. phil/transcription produit
une transcription sous forme de `Track` à relire.
