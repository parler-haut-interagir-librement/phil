<!-- phil-dev source-sha256=ffc786c611edeff540f2b5dfe2c99d6690d39447fd4f258b4921a6a464bc5e18 -->
# Le lecteur

```twig
<twig:PhilAudio:Player :audio="track" :entitled="bought" id="episode-12" class="wide" :bars="160" />
```

| Propriété | Valeur par défaut | Sens |
|---|---|---|
| `audio` | obligatoire | La piste, un `Audio` de phil/audio |
| `entitled` | `false` | Si cet auditeur a acheté la piste, ou peut l'entendre entière pour une autre raison |
| `id` | `phil-audio` | L'identifiant de la figure, dont sont formés les identifiants de ses parties |
| `bars` | `120` | Les barres de la forme d'onde, une au moins |

Les autres attributs, dont `class`, parviennent à la figure.

## Ce qu'il dessine

- Une `<figure>` dont la `<figcaption>` est le titre, marquée « (extrait) » quand seul l'extrait s'entend.
- Le `<audio controls preload="metadata">` du navigateur, nommé par le titre : il joue sans JavaScript, au clavier et
  pour les lecteurs d'écran. Ses sources sont les fichiers que cet auditeur peut entendre, tirés de `Audio::playable()` :
  les fichiers entiers, les fichiers de l'extrait, ou aucun, auquel cas une phrase dit d'acheter la piste. Un navigateur
  qui n'en joue aucun montre un lien vers le premier fichier. Rien ne joue avant que l'auditeur le demande ; aucun
  attribut ne le fait jouer de lui-même.
- La forme d'onde, un `<svg aria-hidden="true">` vide que dessine le contrôleur.
- Les chapitres de la table des matières, dans un `<nav>` nommé par son titre : chacun un `<time>` et un lien, la
  recette Button comme lien, vers l'instant dans le fichier — `episode.mp3#t=120.000`, un fragment de média qu'un
  navigateur ouvre sans JavaScript.
- Chaque transcription qui a des repères, dans un `<details>` dans sa propre langue : chaque repère un lien vers son
  instant, son locuteur, son texte ; et un lien pour télécharger le fichier de la transcription, pour un auditeur qui
  peut entendre la piste entière.

Quand seul l'extrait s'entend, les chapitres et les repères sont ceux qu'il atteint, leurs instants comptés depuis son
début, comme dans son fichier ; avant un achat, il n'y en a aucun.

## Ce qu'ajoute le contrôleur

`phil--audio-bundle--player` ajoute à l'élément audio, sans le remplacer :

- il dessine les barres de la forme d'onde comme un tracé SVG, deux fois, les barres entendues jusque-là découpées selon
  le temps joué ; un clic sur la forme d'onde s'y positionne — le clavier a l'élément audio ;
- un lien de chapitre ou de repère positionne l'audio à son instant au lieu d'ouvrir le fichier, en lecture ou en pause
  comme il était ;
- pendant la lecture, le chapitre et le repère courants reçoivent `aria-current="true"`.

## Les textes

Les textes sont dans le domaine de traduction `phil_audio`, en anglais, en français, en espagnol, en allemand, en
italien et en portugais : `player.excerpt`, `player.purchase_only`, `player.download`, `player.chapters`,
`player.transcript` et `player.download_transcript`.

## Remplacer le gabarit

Une application remplace `@PhilAudio/components/Player.html.twig` sous
`templates/bundles/PhilAudioBundle/components/Player.html.twig`, en gardant l'élément audio comme commande.
