<!-- phil-dev source-sha256=2e5d22b14ec34cafeedc82e231b15d86cf44322ecc07ba0593885a4e27df5ab0 -->
# Cadres tiers

Une vidéo, une carte ou une publication d'un autre site est un cadre qui atteint ce site, avec l'adresse du visiteur, et
le laisse écrire ses propres témoins. `privacy_embed()` le retient jusqu'à ce que sa catégorie de consentement soit
accordée.

```twig
{{ privacy_embed('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'videos', {
    title: 'The talk of the conference',
    preview: asset('media/talk-still.webp'),
    width: 640,
    height: 360,
}) }}
```

Tant que la catégorie n'est pas accordée, la page montre, dans une `figure` :

- l'**aperçu** que sert le site lui-même — une image fixe de la vidéo, une carte dessinée à l'avance —, jamais une image
  du tiers, qui l'atteindrait avant le consentement. Un aperçu doit être un chemin du site : une adresse ailleurs est
  refusée ;
- ce que partage le chargement du cadre, et avec qui : le titre, le fournisseur, la catégorie ;
- un bouton qui **accorde la catégorie** — la décision `grant`, un simple `POST` qui revient à la page, laquelle montre
  alors chaque cadre de cette catégorie ;
- avec JavaScript, un bouton qui **charge ce seul cadre**, sans enregistrer de choix ;
- un lien qui ouvre le contenu sur le site du fournisseur.

Une fois la catégorie accordée, le cadre est un `iframe` avec `loading="lazy"`,
`referrerpolicy="strict-origin-when-cross-origin"`, le titre comme nom accessible et les permissions de `allow`.

| Option | Règle |
|---|---|
| `title` | Obligatoire : le nom du cadre pour un lecteur d'écran, et aussi sa légende. |
| `preview` | Un chemin du site, comme `/media/still.webp`. |
| `width`, `height` | Des pixels, de 1 à 10 000. |
| `allow` | Les permissions du cadre ; par défaut `accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen`. |
| `provider` | Le nom montré à la place de l'hôte. |

La catégorie doit être une catégorie de la politique de consentement, et l'adresse une adresse HTTPS d'un nom d'hôte, lue par
`Url` de `phil/url` comme le fait un navigateur : ni adresse IP, ni `localhost`, ni identifiants, et d'au plus
2 048 caractères. Tout le reste fait échouer le rendu. Une catégorie nécessaire est toujours accordée.

## Les versions qui écrivent moins de traceurs

`privacy_embed()` change l'adresse en la version du service qui écrit le moins dans le navigateur, quand il y en a une,
avec `CookielessVideo` de `phil/url`, dont la documentation liste les adresses qu'il connaît :

| Service | Adresses | Cadre |
|---|---|---|
| YouTube | `watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/`, `v/`, sur `youtube.com`, `youtu.be` et `youtube-nocookie.com` et leurs sous-domaines | `https://www.youtube-nocookie.com/embed/<id>`, avec `start` tiré de `t` ou de `start` (`90`, `90s`, `1h2m3s`) |
| Vimeo | `vimeo.com/<id>`, `vimeo.com/<id>/<hash>`, `player.vimeo.com/video/<id>` | `https://player.vimeo.com/video/<id>?dnt=1`, l'empreinte d'une vidéo non répertoriée gardée comme `h` |

Le lecteur sans témoins de YouTube n'écrit rien avant que la vidéo soit lue, puis garde ses réglages dans le stockage du
navigateur ; le `dnt=1` de Vimeo retire ses données de session. **Aucun des deux n'échappe au tiers** : le cadre
l'atteint encore, avec l'adresse du visiteur, et c'est pourquoi le cadre reste derrière sa catégorie quelle que soit
l'adresse. Déclarez ce qu'écrit le lecteur dans l'inventaire des témoins ([cookie-inventory.md](cookie-inventory.md)).

Une carte n'a pas de telle version. Servez-en une image fixe comme aperçu, et laissez la personne charger la carte
vivante.

Toute autre adresse est gardée telle quelle, son hôte comme fournisseur.

## Avec JavaScript

Le contrôleur Stimulus `phil--privacy-bundle--embed` montre le bouton qui charge un cadre, et remplace l'aperçu par le
cadre quand la personne le presse, ou quand l'événement `phil--privacy-bundle--consent:changed` accorde la catégorie
n'importe où dans la page — depuis la bannière, par exemple. Le cadre attend dans un `<template>`, pour que rien ne soit
téléchargé avant.

Autorisez les hôtes de vos cadres dans le `frame-src` de votre politique de sécurité du contenu.

Remplacez `@PhilPrivacy/embed/frame.html.twig` pour votre balisage ; ses textes sont dans le domaine `phil_privacy`.
