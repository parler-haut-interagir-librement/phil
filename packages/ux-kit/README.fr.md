<!-- phil-dev source-sha256=f40aa77afe415e5c6228a6767278411504794ff3a779e49beab08d3aa238154f -->
# phil/ux-kit

**Le kit PHIL UX pour le Symfony UX Toolkit : les mêmes recettes que le kit Shadcn, habillées par phil-ux, installées
avec `ux:install --kit`.**

Le kit est un dossier de recettes disposé comme le Symfony UX Toolkit l'attend, son `manifest.json` à la racine. Chaque
recette est un composant Twig — et son contrôleur Stimulus quand il bouge —, que `ux:install` copie dans l'application,
où il devient le code de l'application. L'apparence vient de phil-ux, la feuille de style de
[`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css), dont dépend chaque recette ; les recettes
n'apportent que leur Twig et leur Stimulus.

Le kit reprend les noms des recettes, les props, les blocs et les contrôleurs Stimulus du kit Shadcn, pour qu'un bundle
fonctionne avec l'un ou l'autre kit, et ajoute des recettes qui lui sont propres. Leurs versions Tailwind, sous les mêmes
noms et props, sont le kit [`phil/ux-kit-shadcn`](https://github.com/parler-haut-interagir-librement/ux-kit-shadcn).

## État

| Recette | Type | Ce que c'est | État |
|---|---|---|---|
| [Logo](logo/README.md) | composant | Une `Picture` avec ses sources AVIF et WebP et ses densités, changée selon le thème ou la locale, avec un lien vers l'accueil ; `alt` requis | Implémenté et testé |
| [AnimatedHeadline](animated-headline/README.md) | composant | Un titre dont les mots se révèlent par un découpage, joué une fois ou en boucle avec un bouton de pause ; immobile sous le mouvement réduit ; lu en entier par les lecteurs d'écran | Implémenté et testé |
| [Illustration](illustration/README.md) | composant | Une figure qui montre une image choisie parmi des candidates sur le serveur, par un tirage aléatoire à germe | Implémenté et testé |
| [Image](image/README.md) | composant | Une image du contenu, `Image` ou `Picture` avec ses sources AVIF et WebP, par densité ou par largeur, sous les noms de props de Symfony UX Image ; aucun service, aucun JavaScript, les URL `javascript:` et `data:` refusées | Implémenté et testé |
| [AutosaveStatus](autosave-status/README.md) | composant | La région active et l'avis du brouillon d'un formulaire, pour le contrôleur d'enregistrement automatique de `phil/forms-autosave` | Implémenté et testé |
| [ReactionButton](reaction-button/README.md) | composant | Une réaction de `phil/reactions-bundle` : un bouton qui dit s'il est enfoncé, ou du texte simple ; une cible assez large et haute pour un doigt | Implémenté et testé |
| [ReactionPicker](reaction-picker/README.md) | composant | Les autres réactions de `phil/reactions-bundle` : un dévoilement de simples boutons, pour le contrôleur de sélection du bundle | Implémenté et testé |
| [ComingSoon](coming-soon/README.md) | bloc | Une page pleine hauteur en deux parties : logo, surtitre, titre animé, paragraphes, illustration ; empilées sur les petits écrans | Implémenté et testé |
| [Badge](badge/README.md) | composant | Un court libellé : une étiquette, un état, un compte. | Implémenté et testé |
| [Button](button/README.md) | composant | Un bouton, ou un lien qui en a l'air avec `as="a"`. | Implémenté et testé |
| [ButtonGroup](button-group/README.md) | composant | Des boutons qui vont ensemble, joints : un sélecteur de vue, les périodes précédente et suivante. | Implémenté et testé |
| [Card](card/README.md) | composant | Une carte : son image, un en-tête, son contenu et un pied. | Implémenté et testé |
| [Empty](empty/README.md) | composant | Ce qu'une liste montre quand elle n'a rien : une image, un titre, une phrase, et quoi faire. | Implémenté et testé |
| [Popover](popover/README.md) | composant | Un panneau ouvert par un bouton, sur l'API popover du HTML : il s'ouvre et se ferme, Échap et un clic à l'extérieur le ferment, sans JavaScript ; le contrôleur le place à côté de son bouton. | Implémenté et testé |
| [QrCode](qr-code/README.md) | composant | Un code QR dans une figure, avec ce qu'il contient écrit à côté, un lien pour une adresse web, dessiné sur le serveur par `phil/qr-code-bundle`. | Implémenté et testé |
| [Tabs](tabs/README.md) | composant | Des onglets au-dessus de panneaux. Sans JavaScript, chaque panneau s'affiche ; avec lui, un à la fois, les flèches, Début et Fin passant d'un onglet à l'autre. | Implémenté et testé |
| [Accordion](accordion/README.md) | composant | Des sections qui s'ouvrent et se ferment, sur `details` et `summary` : elles fonctionnent sans JavaScript, le navigateur en gardant une seule ouverte à la fois ; le contrôleur ajoute les flèches, Début et Fin. | Implémenté et testé |
| [Dialog](dialog/README.md) | composant | Une fenêtre modale, sur l'élément natif `dialog` et ses invocateurs : le navigateur rend la page inerte, garde le focus à l'intérieur, la ferme sur Échap et rend le focus. | Implémenté et testé |
| [Sheet](sheet/README.md) | composant | Un panneau qui glisse depuis un bord de l'écran : un `Dialog` qui se tient d'un côté, avec le même contrôleur. | Implémenté et testé |
| [Switch](switch/README.md) | composant | Un contrôle marche-arrêt : une case à cocher au rôle `switch`, que le navigateur coche et soumet sans JavaScript. | Implémenté et testé |
| [Tooltip](tooltip/README.md) | composant | Une courte description d'un contrôle, affichée au survol et au focus : on peut la survoler, elle reste jusqu'à ce qu'on la quitte, et Échap la ferme (WCAG 1.4.13). | Implémenté et testé |
| [Pagination](pagination/README.md) | composant | Les liens entre les pages d'une liste. | Implémenté et testé |
| [Carousel](carousel/README.md) | composant | Des diapositives en rangée, sur l'aimantation native du défilement : elles défilent au toucher, au pavé tactile et au clavier sans JavaScript ; le contrôleur ajoute les boutons, les flèches et, sur demande, la lecture automatique. `::scroll-button()` n'étant que dans un seul moteur, les boutons ne s'y fient pas. | Implémenté et testé |
| [Spinner](spinner/README.md) | composant | Ce qui s'affiche pendant que quelque chose se charge ; phil-ux l'immobilise sous `prefers-reduced-motion`. | Implémenté et testé |
| [Skeleton](skeleton/README.md) | composant | Un espace réservé pour un contenu en route, caché aux lecteurs d'écran ; phil-ux l'immobilise sous `prefers-reduced-motion`. | Implémenté et testé |
| [Alert](alert/README.md) | composant | Un message qui se démarque : une erreur, une confirmation. | Implémenté et testé |
| [Avatar](avatar/README.md) | composant | L'image d'une personne, par-dessus les initiales qui s'affichent quand elle manque ou est brisée, sans script. | Implémenté et testé |
| [Input](input/README.md) | composant | Un champ de texte ; tout attribut atteint l'élément. | Implémenté et testé |
| [Label](label/README.md) | composant | Le nom d'un contrôle de formulaire. | Implémenté et testé |
| [Select](select/README.md) | composant | Une liste d'options parmi lesquelles en choisir une, soumise comme un champ du formulaire quand elle a un `name`. Pour une page sans JavaScript, l'élément `select` est habillé par phil-ux à lui seul. | Implémenté et testé |
| [Separator](separator/README.md) | composant | Une ligne entre deux parties d'une page. | Implémenté et testé |
| [Textarea](textarea/README.md) | composant | Un champ de plusieurs lignes. | Implémenté et testé |
| [Checkbox](checkbox/README.md) | composant | Une case à cocher, dessinée par phil-ux. | Implémenté et testé |
| [Breadcrumb](breadcrumb/README.md) | composant | Où la page se situe dans le site. | Implémenté et testé |
| [DropdownMenu](dropdown-menu/README.md) | composant | Un menu d'actions ouvert par un bouton, sur l'API popover : Échap et un clic à l'extérieur le ferment et le focus retourne au bouton. | Implémenté et testé |
| [Table](table/README.md) | composant | Un tableau de données, qui défile de côté dans sa propre boîte sur un petit écran. | Implémenté et testé |
| [AlertDialog](alert-dialog/README.md) | composant | Une question dans une fenêtre modale : Échap la ferme, un clic à l'extérieur non, le focus commence sur la réponse sans danger. | Implémenté et testé |
| [AspectRatio](aspect-ratio/README.md) | composant | Une boîte tenue à un rapport, son image ou sa vidéo la couvrant. | Implémenté et testé |
| [Collapsible](collapsible/README.md) | composant | Une partie de la page qui s'ouvre et se ferme, sur `details` et `summary`, sans JavaScript. | Implémenté et testé |
| [Field](field/README.md) | composant | Un champ de formulaire — libellé, contrôle, aide, erreurs — et des ensembles de champs sous une légende. | Implémenté et testé |
| [Item](item/README.md) | composant | Une entrée d'une liste : son média, son titre et sa description, ses actions. | Implémenté et testé |
| [Kbd](kbd/README.md) | composant | Une touche du clavier, ou les touches d'un raccourci, sur `kbd`. | Implémenté et testé |
| [NativeSelect](native-select/README.md) | composant | L'élément `select` du navigateur, habillé par phil-ux. | Implémenté et testé |
| [Progress](progress/README.md) | composant | La progression d'une tâche, sur l'élément natif `progress`. | Implémenté et testé |
| [RadioGroup](radio-group/README.md) | composant | Des boutons radio qui partagent un nom, entre lesquels le navigateur se déplace avec les flèches. | Implémenté et testé |
| [ScrollArea](scroll-area/README.md) | composant | Une boîte dont le contenu défile à l'intérieur, une région que le clavier atteint quand elle est nommée. | Implémenté et testé |
| [Toggle](toggle/README.md) | composant | Un bouton qui reste enfoncé, sur une case à cocher dans son libellé, sans JavaScript. | Implémenté et testé |
| [ToggleGroup](toggle-group/README.md) | composant | Des boutons à bascule qui vont ensemble, sur des boutons radio ou des cases à cocher, sans JavaScript. | Implémenté et testé |
| [LoginForm](login-form/README.md) | bloc | Un formulaire de connexion dans une carte, pour le form login de Symfony Security. | Implémenté et testé |
| [SignupForm](signup-form/README.md) | bloc | Un formulaire d'inscription dans une carte, chaque champ nommant ses erreurs, un mot de passe jamais réécrit. | Implémenté et testé |
| [ColorPicker](color-picker/README.md) | composant | Le sélecteur de couleur du navigateur, sa valeur hexadécimale et des pastilles parmi lesquelles choisir. Le sélecteur est le champ : il fonctionne seul, sans script. | Implémenté et testé |
| [DatePicker](date-picker/README.md) | composant | Le champ de date du navigateur, qui fonctionne seul, et un bouton qui ouvre un mois sur une grille, dans une bulle. | Implémenté et testé |
| [CopyButton](copy-button/README.md) | composant | Un bouton qui copie un texte, donné ou lu dans un autre élément, et le dit. | Implémenté et testé |
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
| [Chart](chart/README.md) | composant | Un graphique dessiné par le serveur en SVG, en barres ou en lignes, avec le tableau de ses valeurs. | Implémenté et testé |
| [ChartJs](chart-js/README.md) | composant | Un graphique de Symfony UX Chart.js dans une figure, avec sa légende et le tableau de ses valeurs. | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | `ux:install` depuis le dépôt public, les fichiers à copier d'ici là, la feuille de style. |
| [Recettes](doc/fr/recipes.md) | Chaque recette, ses props, ses blocs et son contrôleur. |
| [Une page « bientôt »](doc/fr/coming-soon.md) | Une page entière bâtie sur le bloc `ComingSoon`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application change, et comment. |
| [Tests](doc/fr/testing.md) | Comment le kit est testé. |
| [Référence](doc/fr/reference.md) | Chaque fichier du kit. |

## Démarrage rapide

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
<twig:ComingSoon
    logoSrc="{{ asset('images/logo.svg') }}"
    logoAlt="Studio"
    eyebrow="{{ 'coming_soon'|trans }}"
    words="Studio"
    :paragraphs="['site.info'|trans]"
    :illustrations="[asset('images/main.png')]"
    illustrationAlt=""
/>
```

## Prérequis

Symfony UX Twig Component 3.7 ou plus récent ; Symfony UX Toolkit 3.7 ou plus récent pour installer les recettes ; le
StimulusBundle pour les recettes qui bougent ; `twig/extra-bundle` et `twig/html-extra` pour `Image` ;
`phil/qr-code-bundle` pour `QrCode` ; Symfony UX Chart.js pour `ChartJs` ; la feuille de style phil-ux de `phil/ux-css`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, faisant affaire sous le nom Le Gnome Archiviste ; un projet Parler Haut Interagir Librement.
Voir [LICENSE](LICENSE).
