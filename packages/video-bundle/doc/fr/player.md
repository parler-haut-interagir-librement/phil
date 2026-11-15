<!-- phil-dev source-sha256=5409f47caf5a0e3584d0b38829b63c65093086534be0843593324aa5f837acbf -->
# Le lecteur

```twig
<twig:PhilVideo:Player :video="video" id="talk-12" class="wide" />
```

| Propriété | Valeur par défaut | Sens |
|---|---|---|
| `video` | obligatoire | Une `HostedVideo` ou une `LinkedVideo` |
| `id` | `phil-video` | L'identifiant du lecteur, dont sont formés les identifiants de ses parties |

Les autres attributs parviennent à l'élément racine.

## Toute vidéo

| Propriété | Sens |
|---|---|
| `id` | Lettres, chiffres, `_` et `-`, 64 au plus : le nom de ses visionnements |
| `title` | Son titre, jamais vide |
| `width`, `height` | Sa taille en pixels, 1280 × 720 par défaut, afin que sa place soit réservée avant son chargement |
| `poster` | L'image montrée avant la lecture : une image que le site sert |
| `transcript`, `transcriptLanguage` | Une `Track` de phil/timed-text, et sa langue BCP 47 |
| `chapters` | Une `ChapterList` de phil/timed-text |

## Une vidéo hébergée

`HostedVideo` ajoute ses fichiers, des `VideoSource` — une adresse et un type `video/*`, dans l'ordre où le navigateur
les essaie ; un au moins —, un flux HLS, et ses `TextTrack` : des fichiers WebVTT de sous-titres pour malentendants, de
sous-titres, de descriptions ou de chapitres, chacun avec sa langue, son libellé et l'indication qu'il s'affiche
aussitôt.

Elle est dessinée avec la recette Video : l'élément vidéo du navigateur avec ses commandes, son affiche, ses pistes,
rien de récupéré avant que le spectateur la joue, et aucune lecture automatique. La recette écarte un fichier, une
affiche ou une piste d'un hôte que `image_origin()` refuse.

Le flux est pour un service qui sert du HLS : là où le navigateur lit le HLS, le contrôleur le donne à l'élément vidéo ;
ailleurs, les fichiers jouent. Une vidéo hébergée a toujours un fichier, afin que tout navigateur la joue.

`TextTrackResponse::webVtt($track)` sert une `Track` depuis un contrôleur de l'application, avec le type `text/vtt` et
`nosniff`.

## Une vidéo liée

`LinkedVideo::fromUrl($id, $title, $address, $poster)` lit une adresse de YouTube — `watch?v=`, `youtu.be/`, `embed/`,
`shorts/`, `live/` — ou de Vimeo, son heure de début conservée, par `CookielessVideo` de phil/url ; toute autre adresse
est refusée avec `InvalidVideo`.

Elle est dessinée comme une façade : l'affiche, un bouton que montre le contrôleur — « Lire la vidéo : … » —, une phrase
qui dit que la lecture charge le lecteur de la plateforme, et un lien vers la plateforme pour un spectateur sans
JavaScript. Le bouton met le lecteur à la place de la façade, à partir de `embed()` : l'adresse sans témoin, qui joue
aussitôt puisque le spectateur l'a demandé, dans un cadre portant le titre de la vidéo, avec une politique de référent
stricte et un bac à sable. L'affiche est l'image du site : une image de la plateforme l'atteindrait avant que le
spectateur le demande.

## Chapitres et transcription

Les chapitres de la table des matières, dans un `<nav>` nommé par son titre, et la transcription, dans un `<details>`
dans sa langue, se placent à côté de la vidéo. Pour une vidéo hébergée, chaque chapitre et chaque repère est un lien — la
recette Button — vers son instant dans le premier fichier, un fragment de média qu'un navigateur ouvre sans JavaScript et
que le contrôleur transforme en positionnement ; le chapitre et le repère courants reçoivent `aria-current="true"`. Pour
une vidéo liée, dont le lecteur est celui de la plateforme, ce sont du texte.

## Les textes

Les textes sont dans le domaine de traduction `phil_video`, en anglais, en français, en espagnol, en allemand, en
italien et en portugais : `player.play`, `player.notice`, `player.watch`, `player.download`, `player.chapters` et
`player.transcript`.
