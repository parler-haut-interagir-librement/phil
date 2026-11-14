<!-- phil-dev source-sha256=e8d6ea333fc73204975e163f8c2a3abbc47dbd7c3c1d3fc02bc7bce1ff5bef8f -->
# phil/ux-kit-shadcn

**Des versions Tailwind, dans le style du kit Shadcn, des recettes supplémentaires de PHIL UX, pour `ux:install --kit`.**

Le kit PHIL UX reprend les recettes du kit Shadcn du Symfony UX Toolkit et y ajoute des recettes qui lui sont propres,
que des bundles PHIL peuvent exiger. Ce kit-ci contient ces seules recettes supplémentaires, écrites en Tailwind sur les
jetons du kit Shadcn (`bg-background`, `text-muted-foreground`), sous les mêmes noms, propriétés, blocs et contrôleurs
Stimulus que dans [`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit). Une application qui
utilise le kit Shadcn installe les recettes Shadcn depuis celui-ci et les recettes PHIL depuis ce kit-ci ; un bundle
produit son rendu avec l'un ou l'autre.

Deux noms sont communs avec le kit Shadcn. `Carousel` est ici sous les propriétés du kit PHIL UX, sur l'accrochage de
défilement natif plutôt que sur une bibliothèque de carrousel : installez-le depuis l'un ou l'autre kit, car ils
écrivent les mêmes fichiers. Le `DatePicker` du kit Shadcn, une bulle autour de son calendrier, est celui à installer :
le champ de date natif du kit PHIL UX n'est pas repris ici.

## État

| Recette | Type | Ce que c'est | État |
|---|---|---|---|
| [Logo](logo/README.md) | composant | Une `Picture` avec ses sources AVIF et WebP et ses densités, ses variantes pour les couleurs forcées, les systèmes sombres et le mouvement réduit, changée selon le thème ou la locale, avec un lien vers l'accueil ; `alt` requis | Implémenté et testé |
| [AnimatedHeadline](animated-headline/README.md) | composant | Un titre dont les mots se révèlent par un découpage, joué une fois ou en boucle avec un bouton de pause ; immobile sous le mouvement réduit ; lu en entier par les lecteurs d'écran | Implémenté et testé |
| [Illustration](illustration/README.md) | composant | Une figure qui montre une image choisie parmi des candidates sur le serveur, par un tirage aléatoire à germe | Implémenté et testé |
| [Image](image/README.md) | composant | Une image du contenu, `Image` ou `Picture` avec ses sources AVIF et WebP, par densité ou par largeur, sous les noms de props de Symfony UX Image : l'image principale préchargée par WebLink, `fetchpriority="low"` pour une image loin en bas, une couleur d'attente, un aperçu flou facultatif, des variantes pour les couleurs forcées, les systèmes sombres et le mouvement réduit, une description longue, crédit, licence et création, les images d'autres hôtes refusées sauf si listés, un `ImageObject` en JSON-LD, des cadres, des jetons et du pixel art, un nom pour les transitions de vue, lisible sur papier et de droite à gauche ; aucun style en ligne, fonctionne sous une politique de sécurité du contenu stricte | Implémenté et testé |
| [Gallery](gallery/README.md) | composant | Une liste de figures en une grille sur une requête de conteneur, chaque image ouverte dans une `Lightbox` sur demande ; une longue galerie rendue à mesure qu'elle approche, par `content-visibility` | Implémenté et testé |
| [Lightbox](lightbox/README.md) | composant | Une image agrandie dans un `dialog` modal natif, ouvert par les invocateurs de HTML, le contrôleur `lightbox` prenant le relais là où le navigateur ne les exécute pas | Implémenté et testé |
| [Hotspots](hotspots/README.md) | composant | Une image avec des points d'intérêt, une liste de liens par-dessus, chacun placé par des utilitaires et un `attr()` typé, jamais par un style en ligne ; une liste sous l'image sur un téléphone | Implémenté et testé |
| [Hero](hero/README.md) | composant | Une image sur toute la boîte qui reste une vraie image, l'image principale de la page par défaut, et les mots de la page par-dessus sur un voile | Implémenté et testé |
| [Video](video/README.md) | composant | Une vidéo avec les commandes du navigateur qui ne joue jamais d'elle-même : rien de demandé avant la lecture, son affiche, ses sous-titres et sa transcription | Implémenté et testé |
| [Figure](figure/README.md) | composant | Plusieurs images en grille sous une seule légende, un crédit et une licence | Implémenté et testé |
| [QrCode](qr-code/README.md) | composant | Un code QR dessiné sur le serveur par `phil/qr-code-bundle`, dans une figure avec ce qu'il contient écrit à côté | Implémenté et testé |
| [AutosaveStatus](autosave-status/README.md) | composant | La région active et l'avis du brouillon d'un formulaire, pour le contrôleur d'enregistrement automatique de `phil/forms-autosave` | Implémenté et testé |
| [ReactionButton](reaction-button/README.md) | composant | Une réaction de `phil/reactions-bundle` : un bouton qui dit s'il est enfoncé, ou du texte simple ; une cible assez large et haute pour un doigt | Implémenté et testé |
| [ReactionPicker](reaction-picker/README.md) | composant | Les autres réactions de `phil/reactions-bundle` : un dévoilement de simples boutons, pour le contrôleur de sélection du bundle | Implémenté et testé |
| [ColorPicker](color-picker/README.md) | composant | Le sélecteur de couleur du navigateur, sa valeur hexadécimale et des pastilles parmi lesquelles choisir. Le sélecteur est le champ : il fonctionne seul, sans script. | Implémenté et testé |
| [CopyButton](copy-button/README.md) | composant | Un bouton qui copie un texte, donné ou lu dans un autre élément, et le dit. | Implémenté et testé |
| [Celebration](celebration/README.md) | composant | Un message dit dans une région active, et des confettis ou des feux d'artifice dessinés sur la page dans un canvas pendant quelques secondes, aux jetons `--primary` et `--chart-*` ; le message seul là où le mouvement est réduit. Jouée d'emblée, sur un événement ou par un bouton. | Implémenté et testé |
| [AnimatedButton](animated-button/README.md) | composant | Un bouton, ou un lien quand il a une adresse, qui bouge sous le pointeur et le focus : une lumière qui le balaie, une élévation ou sa flèche poussée vers l'avant. Du CSS seulement. | Implémenté et testé |
| [Stepper](stepper/README.md) | composant | Les étapes d'un processus, celles qui sont terminées marquées, la courante annoncée, lues de haut en bas sur un téléphone. | Implémenté et testé |
| [ActivityCalendar](activity-calendar/README.md) | composant | L'activité dans le temps : une case par jour, chacune lue par son libellé, la couleur jamais seule. Plus large que l'écran, il défile de lui-même et prend le focus pour le faire. | Implémenté et testé |
| [Tree](tree/README.md) | composant | Un arbre d'éléments, selon le motif WAI-ARIA. | Implémenté et testé |
| [Rating](rating/README.md) | composant | Une note sur un nombre d'étoiles : des boutons radio avec un nom, une seule image sans nom, par demi-étoiles. | Implémenté et testé |
| [NumberInput](number-input/README.md) | composant | Un champ numérique avec un bouton de chaque côté. | Implémenté et testé |
| [FileInput](file-input/README.md) | composant | Un champ de fichier qui accepte aussi un dépôt, et qui liste ce qu'il contient avec la taille de chacun. | Implémenté et testé |
| [Tag](tag/README.md) | composant | Une courte étiquette, qu'un bouton peut retirer. | Implémenté et testé |
| [RelativeTime](relative-time/README.md) | composant | Un moment écrit par le serveur comme une date, puis par un script comme le temps écoulé depuis maintenant, qu'il garde juste. | Implémenté et testé |
| [ProgressRing](progress-ring/README.md) | composant | Une progression en anneau, avec le pourcentage au centre. Du CSS et du SVG seulement. | Implémenté et testé |
| [Sparkline](sparkline/README.md) | composant | Une petite ligne de valeurs, dessinée par le serveur. | Implémenté et testé |
| [BeforeAfter](before-after/README.md) | composant | Deux images l'une sur l'autre, la première coupée là où le dit un curseur. | Implémenté et testé |
| [ComingSoon](coming-soon/README.md) | bloc | Une page pleine hauteur en deux parties : logo, surtitre, titre animé, paragraphes, illustration ; empilées sur les petits écrans | Implémenté et testé |
| [LoginForm](login-form/README.md) | bloc | Un formulaire de connexion dans une carte, pour le form login de Symfony Security. | Implémenté et testé |
| [SignupForm](signup-form/README.md) | bloc | Un formulaire d'inscription dans une carte, chaque champ nommant ses erreurs, un mot de passe jamais réécrit. | Implémenté et testé |
| [Chart](chart/README.md) | composant | Un graphique dessiné par le serveur en SVG, en barres ou en lignes, ses séries dans les jetons de graphique du kit Shadcn, avec le tableau de ses valeurs. | Implémenté et testé |
| [Carousel](carousel/README.md) | composant | Des diapositives en rangée sur l'accrochage de défilement natif du navigateur, les boutons du kit de chaque côté, une lecture automatique facultative avec son bouton de pause, toujours arrêtable en mouvement réduit ; aucune bibliothèque de carrousel | Implémenté et testé |
| [ChartJs](chart-js/README.md) | composant | Un graphique de Symfony UX Chart.js dans une figure, avec sa légende et le tableau de ses valeurs. | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Tailwind et le kit Shadcn, `ux:install`, les fichiers à copier tant que le dépôt n'est pas publié. |
| [Différences](doc/fr/differences.md) | Ce qui diffère du kit PHIL UX : les classes seulement. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application change, et comment. |
| [Tests](doc/fr/testing.md) | Comment le kit est testé. |
| [Référence](doc/fr/reference.md) | Chaque fichier du kit. |

## Démarrage rapide

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit-shadcn
```

```twig
<twig:ComingSoon logoSrc="/images/logo.svg" logoAlt="Studio" eyebrow="Coming soon" words="Studio" />
```

## Prérequis

Tailwind CSS 4 avec la feuille de style du kit Shadcn et tw-animate-css, comme le kit Shadcn les installe ; Symfony UX
Twig Component 3.7 ou plus récent ; Symfony UX Toolkit 3.7.1 ou plus récent pour installer les recettes ; le
StimulusBundle pour les recettes dotées d'un contrôleur (`AnimatedHeadline`, `Carousel`, `Celebration`, `Lightbox`,
`ColorPicker`, `CopyButton`, `Tree`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`) et l'aperçu flou d'`Image` ; `twig/extra-bundle`, `twig/html-extra`,
`symfony/web-link` et `phil/ux-kit`, dont le bundle liste les hôtes dont une page peut montrer les images, pour
`Image` ; les jetons `--chart-1` à `--chart-5` du kit Shadcn pour `Chart` ; Symfony UX Chart.js pour `ChartJs`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
