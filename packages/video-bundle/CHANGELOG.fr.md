<!-- phil-dev source-sha256=14d8ef6d1788158d34a6619a928cf14c57ba263c53d08663ed6ff01c00658238 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/video-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

### Sécurité

- `ProgressController`, dont la vérification de l'origine n'est pas une connexion, comparait le corps à son kilooctet
  après l'avoir lu en entier. Il lit le flux jusqu'à un octet au-delà de `MAX_BYTES` et pas plus loin.

### Modifié

- Les prérequis de développement prennent `twig/twig` et `twig/html-extra` 3.31.1 ou plus récent, qui corrige les avis
  sur son bac à sable publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Exige la dernière mineure de chaque dépendance (`symfony/service-contracts` 3.8), and of each development requirement
  (`twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `twig/extra-bundle` 3.31, `twig/html-extra` 3.31).

## 0.1.0-alpha - 2026-11-15

### Ajouté

- Les vidéos : `HostedVideo` avec ses `VideoSource`, un flux HLS et ses `TextTrack` ; `LinkedVideo` de YouTube ou de
  Vimeo, lue de l'adresse qu'un auteur colle, son lecteur sans témoins tiré de phil/url ; une affiche, une transcription
  et des chapitres de phil/timed-text pour les deux.
- Le lecteur `PhilVideo:Player` : une vidéo hébergée dessinée avec la recette Video du kit, sans lecture automatique,
  rien de récupéré avant que la personne le demande ; une vidéo liée derrière une façade faite de l'image du site, d'un
  bouton et d'un lien vers la plateforme ; les chapitres et la transcription à côté, des liens vers leurs instants pour
  une vidéo hébergée.
- Le contrôleur Stimulus `player` et sa feuille de style : le lecteur de la plateforme chargé seulement au clic, dans un
  cadre en bac à sable ; le flux HLS là où le navigateur le lit ; la recherche dans la vidéo ; le chapitre et la
  réplique courants marqués ; les parties regardées envoyées seulement quand la page dit que la personne a consenti.
- La progression du visionnement avec consentement : `ViewingProgress`, `Heatmap`, `ViewingTracker`, les ports
  `ViewingConsent` et `ViewingStore`, l'événement `VideoProgressed`, `NoViewingConsent`, `InMemoryViewingStore` et
  `CacheViewingStore`.
- `ProgressController` et sa route : un kilo-octet depuis une page du site, `204` compté ou non, jamais mis en cache.
- `TextTrackResponse` : une `Track` servie en WebVTT.
- La configuration `phil_video` : le suivi, son point d'accès, le consentement et le stockage, et le sous-dossier des
  recettes du kit.
- Les textes du lecteur en anglais, en français, en espagnol, en allemand, en italien et en portugais.
- Les cibles de fuzzing `linked-url` et `progress`.
- Le squelette du paquet, sa documentation dans `doc/`, `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
