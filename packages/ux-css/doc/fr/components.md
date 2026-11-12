<!-- phil-dev source-sha256=138c640a3138f1ed221d694386f570a73b30c3e1a01f8717b7932ac78cc930ca -->
# Composants

Ce que la base sans classe ne peut pas deviner, dans la couche `phil.components`, et les utilitaires, en tout dernier.
Chaque classe dit ce qu'est l'élément ou ce qu'il a ; les recettes du kit PHIL UX écrivent ces noms, et seuls Twig et
Stimulus viennent avec elles.

## Tonalités et tailles

Une tonalité est une classe qui définit six couleurs — le remplissage, la bordure et la couleur sur ce remplissage,
chacun discret ou fort — sur tout composant qui les lit. Elle fonctionne aussi bien sur un bouton, une pastille et une
alerte, et un bouton dans un avis coloré garde la sienne.

| Tonalité | Rôle de la grille |
|---|---|
| `isPrimary` | brand |
| `isNeutral` | neutral |
| `isDanger` | danger |
| `isSuccess` | success |
| `isWarning` | warning |
| `isInfo` | info |

`isSmall` et `isLarge` définissent la hauteur du contrôle (`--phil-control-sm` ou `-lg`), son remplissage et la taille
de son texte. Elles s'héritent : sur un formulaire ou une barre d'outils, elles dimensionnent chaque contrôle qu'il
contient.

## Boutons

Un `button`, un champ de soumission et un élément de `role="button"` sont pleins et dans la marque sans classe ;
`isButton` donne la même apparence à un lien ou à un `summary`. Les classes se combinent : `isDanger isOutline` est un
contour rouge.

| Classe | Ce qu'elle fait |
|---|---|
| `isButton` | L'apparence d'un bouton, sur un élément qui n'en est pas un |
| `isPrimary` | Le plein de la marque, le défaut dit tout haut |
| `isSecondary` | Le remplissage discret de la tonalité, neutre par défaut |
| `isGhost` | Aucun remplissage tant que le pointeur n'est pas dessus, neutre par défaut |
| `isOutline` | Une bordure forte et aucun remplissage, dans la tonalité du bouton |
| `isLink` | Ni remplissage ni bordure, souligné dans la couleur des liens ; sur une pastille aussi |
| `isDanger` | La tonalité de danger, pour ce qui détruit |
| `isSmall`, `isLarge` | Un contrôle de 2rem ou de 3.25rem ; le défaut est la cible tactile de 2.75rem |
| `isLoading` | Un indicateur de chargement par-dessus le contenu, qui garde sa place ; pas de pointeur. Sur toute boîte positionnée, les boutons cachant leur libellé |
| `hasIcon` | Dimensionne un `svg` ou une `img` enfant à 1.25em ; sur un `isAlert`, le met dans une colonne à lui |
| `isIconOnly` | Un bouton carré, son nom dans `aria-label` |

`aria-pressed="true"` et `aria-current` font passer un bouton au remplissage discret avec une bordure forte, quelle que
soit son apparence. Un survol ou un appui mêle le remplissage vers la couleur du texte, ce qui élève son contraste avec
le libellé. Les propriétés personnalisées `--phil-button-fill`, `--phil-button-fill-hover`, `--phil-button-fill-active`,
`--phil-button-on` et `--phil-button-border` changent un bouton.

`isButtonGroup` joint des boutons côte à côte, qui partagent leurs bordures, celui qui a le focus ou qui est enfoncé
au-dessus de ses voisins ; `isVertical` les empile. `isButtonGroupText` est du texte simple parmi eux, à leur hauteur.

## Pastilles et alertes

`isBadge` : un état court, neutre et discret par défaut ; `isLoud` le rend plein et `isOutline` ne garde que sa
bordure.

`isAlert` : un avis dans la page, discret, avec un filet fort sur son côté de début et des liens dans sa propre couleur.
Le gabarit lui donne le `role` dont il a besoin, `status` ou `alert`. `isLoud` le rend plein ; `hasIcon` met une icône
dans une colonne.

```html
<div class="isAlert isWarning hasIcon" role="status">
    <svg aria-hidden="true">…</svg>
    <p>The payment is late.</p>
</div>
```

## Cartes et états vides

`isCard` : une surface avec une bordure et une ombre. Son image `isCardMedia` prend le rapport de
`--phil-card-media-ratio` (4 / 3), son `isCardBody` l'espace qui reste, son `isCardTitle` le titre, et un `header` et un
`footer` directs sont séparés par un filet. Un `isCardLink` couvre toute la carte et y déplace l'anneau de focus.

`isEmptyState` : un cadre en tirets, un titre et ce qu'il faut faire, centrés.

## Onglets

`isTabs` contient un élément de `role="tablist"`, ses boutons `role="tab"` et ses panneaux `role="tabpanel"` ;
`aria-selected` marque l'onglet, et la barre défile quand elle ne tient pas. Le clavier est celui du contrôleur.

## Panneaux latéraux

`isSheet`, sur un `dialog` : un panneau contre un bord, le côté de fin par défaut, `isStart`, `isTop` ou `isBottom`
sinon, qui glisse là où le navigateur sait animer la couche supérieure. `--phil-dialog-size` est sa largeur.

## Interrupteurs

`isSwitch`, ou `role="switch"` seul, sur un `input type="checkbox"` : une piste et un curseur, dans la marque quand il
est coché, qui suivent le sens d'écriture et sont repeints sous les couleurs forcées.

## Infobulles

`isTooltip` : la bulle, inversée. En `popover`, un contrôleur la place. Dans une boîte `hasTooltip`, elle s'affiche au
survol et au focus sans JavaScript ; la fermer avec Échap demande le contrôleur.

## Menus

`isDropdown`, sur un `details` ou un conteneur, contient un `isDropdownMenu` qui s'ouvre sous son bouton, ou un
`popover` qu'un contrôleur place. Ses liens, ses boutons et ses `role="menuitem"` sont des cibles de pleine largeur ; un
`hr` les sépare.

```html
<details class="isDropdown">
    <summary class="isButton isSecondary">Actions</summary>
    <ul class="isDropdownMenu"><li><a href="/edit">Edit</a></li></ul>
</details>
```

## Accordéons

`isAccordion` contient des `details` qui se lisent comme un tout, séparés par des filets. Un `name` partagé entre eux en
garde un seul ouvert à la fois, nativement.

## Parcours dans les pages

`isBreadcrumb`, sur un `nav` qui contient une liste : un séparateur entre les éléments, dessiné et caché aux lecteurs
d'écran.

`isPagination`, sur un `nav` qui contient une liste de liens ou de boutons, chacun une cible tactile ; `aria-current`
marque la page et `aria-disabled="true"` un lien qui ne mène nulle part.

`isStepper`, sur un `ol` : des étapes numérotées reliées par une ligne, la courante `aria-current="step"`, les
terminées `isDone` ; elles se lisent de haut en bas sur un téléphone. `isStepperItem` et `isStepperLabel` sont les
parties d'une étape.

## Attentes et espaces réservés

`isSpinner` : un anneau de la taille du texte qui l'entoure ; donnez-lui `role="status"` et un nom. `isSkeleton` : la
place pulsante de ce qui se charge encore. Sous le mouvement réduit, les deux restent immobiles.

## Avatars, séparateurs, carrousels

`isAvatar` : une image ronde, ou des initiales sur le neutre discret, à la taille d'un contrôle : `isSmall` et
`isLarge` s'appliquent.

`isSeparator` : un filet, sur un `hr`, ou de part et d'autre d'un court libellé sur un `div` ; `isVertical` se dresse
dans une rangée.

`isCarousel` : des diapositives en rangée, aimantées, chacune de largeur `--phil-carousel-item` (100 %), avec
`--phil-carousel-gap` entre elles. Le gabarit le rend focalisable, pour que le clavier puisse le faire défiler.

## Le calendrier d'activité

`isActivityCalendar` : une case par jour, sept rangées, autant de colonnes qu'il y a de semaines. Chaque enfant dit son
niveau par `data-level`, de 0 à 4, et son sens par un titre ou un libellé : deux niveaux d'écart atteignent 3:1, la
couleur n'est donc jamais seule. `isActivityCalendarDay` est un jour et `isActivityCalendarBlank` une case vide avant le
premier ; `hasActivityCalendar` contient le calendrier et `isActivityLegend`, les cinq niveaux entre deux mots.

## Tableaux

`isStriped` raye les rangées d'un tableau, `hasHover` éclaire celle qui est sous le pointeur, et `isScrollable` est la
boîte qui fait défiler un tableau trop large pour la page, quand un `figure` ne convient pas.

## Codes QR et code coloré

`isQrCode` contient le dessin et son `isQrCodeCaption`.

Les classes `hl-*` qu'écrit un colorateur (`hl-keyword`, `hl-string`, `hl-number`, `hl-comment`, `hl-function`, `hl-tag`
et leurs semblables) prennent chacune un rôle de couleur lu sur une surface, par `--phil-hl-keyword`,
`--phil-hl-string`, `--phil-hl-number`, `--phil-hl-comment`, `--phil-hl-name` et `--phil-hl-markup`.

## Les médias UJO sur papier

UJO (Universal JSON Output) produit un cadre, une vidéo ou un son suivi de `.ujo-print-fallback` : son affiche, son
titre, son adresse et un code QR. La feuille de style cache ce repli partout sauf sur papier, et sur papier l'affiche à
la place du lecteur.

## Le surtitre

`isEyebrow` : un court libellé au-dessus d'un titre — « Bientôt », une catégorie, une date —, petit, atténué et jamais
un titre à lui seul. Avec son titre dans un `hgroup`, il se lit comme une partie du titre.

```html
<hgroup>
    <p class="isEyebrow">Coming soon</p>
    <h1 class="isHeadline">Studio</h1>
</hgroup>
```

## Le titre et son dévoilement par découpage

`isHeadline` : un titre d'affichage, fluide de la taille de texte `2xl` à `4xl`.

`isReveal` : les mots d'un titre empilés dans une boîte, celui marqué `isCurrent` affiché. Sans JavaScript, il reste
immobile. Le contrôleur `animated-headline` du kit PHIL UX le pilote :

| État ou propriété | Posé par | Effet |
|---|---|---|
| `isRevealing` | Le contrôleur, pendant qu'il pilote le découpage | Le curseur s'affiche du côté de fin |
| `isPulsing` | Le contrôleur, pendant qu'un mot se repose entre deux mouvements | Le curseur clignote |
| `isCurrent` | Le gabarit sur le premier mot, puis le contrôleur | Le mot affiché |
| `--phil-reveal-size` | Le contrôleur | La largeur du découpage, en transition |
| `--phil-reveal-duration` | Le contrôleur, d'après sa valeur | La durée de la transition |
| `--phil-reveal-caret` | La feuille de style, 0.06em | La largeur du curseur |

Une fois les mots joués, le contrôleur retire `isRevealing`, `isPulsing` et `--phil-reveal-size` : le découpage revient
à sa largeur naturelle. `isRevealToggle` est le bouton de pause et de lecture d'un titre en boucle : un petit texte dans
la ligne du titre, sur la surface surélevée avec une bordure, caché tant qu'il a l'attribut `hidden`.

Sous `prefers-reduced-motion`, le découpage garde sa largeur naturelle, le curseur est caché et rien n'est en
transition. Les mots animés sont faits pour être `aria-hidden`, le titre entier dans une copie `isVisuallyHidden`.

```html
<h1 class="isHeadline">
    <span class="isVisuallyHidden">We make sites, apps, games</span>
    <span aria-hidden="true">We make <span class="isReveal" aria-live="off">
        <span class="isCurrent">sites</span><span>apps</span><span>games</span>
    </span></span>
</h1>
```

## Le logo

`isLogo` : un bloc en ligne dont l'image fait `--phil-logo-size` de haut (3rem), sa largeur d'après son rapport.

## L'image

`isImage` : une image du contenu en bloc, jamais plus large que son conteneur, sa hauteur suivant sa largeur, pour que
les `width` et `height` écrits sur elle réservent sa place à son rapport avant qu'elle se charge, sans l'étirer quand
le conteneur est plus étroit.

Avec elle, sur le même élément, jamais dans un attribut `style` :

| Classe | Ce qu'elle fait |
|---|---|
| `hasLayoutFixed` | À sa largeur quel que soit le conteneur, `max-inline-size: none` |
| `hasLayoutFullWidth` | Sur toute la largeur du conteneur, `inline-size: 100%` |
| `hasRatio16x9`, `hasRatio4x3`, `hasRatio3x2`, `hasRatio1x1`, `hasRatio21x9`, `hasRatio3x4`, `hasRatio2x3`, `hasRatio9x16` | Le rapport de sa boîte |
| `hasRatio` | Le rapport de `--phil-image-ratio`, qu'une classe du site pose |
| `hasFitCover`, `hasFitContain` | Comment l'image remplit sa boîte : rognée pour la couvrir, ou entière dedans |
| `hasPositionCenter`, `hasPositionTop`, `hasPositionBottom`, `hasPositionLeft`, `hasPositionRight` | Où l'image est rognée |

Un rapport propre vient d'une classe de la feuille du site, qu'une politique de sécurité du contenu à nonce garde, là où
un `style` en ligne serait retiré :

```css
.isHeroPhoto {
    --phil-image-ratio: 5 / 2;
}
```

```html
<img class="isImage hasRatio hasFitCover isHeroPhoto" src="/hall.jpg" alt="The hall" width="1500" height="1000">
```

## Réactions

`isReaction` : une réaction, une cible de 2.75rem de large et de haut quoi qu'elle contienne, marquée quand son
`aria-pressed` est vrai ; les recettes du kit l'emploient sur un bouton, un résumé ou du texte simple.
`isReactionPicker` contient les autres réactions, et `isReactionPickerPanel` les pose par-dessus ce qui suit.

## Prose

`isProse` : un long texte écrit par des personnes — une mesure lisible, le même écart entre les blocs
(`--phil-prose-gap`), plus d'espace avant les titres, des médias arrondis.

## Les parties des composants

Chaque partie d'un composant a sa classe, pour qu'une page écrite à la main et les recettes du kit PHIL UX se lisent de
la même façon. Aucune de ces règles ne regarde un attribut de données des recettes.

| Composant | Parties |
|---|---|
| Alerte | `isAlertTitle`, `isAlertDescription`, `isAlertAction` |
| Avatar | `isAvatarImage`, `isAvatarFallback`, `isAvatarBadge`, sur le bord de l'image ; `isAvatarGroup` les fait se chevaucher, `isAvatarGroupCount` compte ceux qui sont laissés de côté |
| Carte | `isCardHeader`, `isCardBody`, `isCardTitle`, `isCardDescription`, `isCardFooter`, `isCardAction` au bout de l'en-tête ; `isSmall` sur la carte les resserre |
| État vide | `isEmptyHeader`, `isEmptyMedia`, `isEmptyTitle`, `isEmptyDescription`, `isEmptyContent` |
| Onglets | `isTabList`, `isTab`, `isTabPanel` ; sur le panneau, `isCurrent` dit lequel s'affiche tant qu'un script n'a pas encore tourné ; `isVertical` sur le `isTabs` |
| Accordéon | `isAccordionItem`, `isAccordionTrigger`, `isAccordionContent` |
| Dialogue | `hasDialog` garde dans la ligne ce qui l'ouvre ; `isDialog`, `isDialogHeader`, `isDialogTitle`, `isDialogDescription`, `isDialogFooter`, `isDialogClose` dans le coin |
| Panneau latéral | `isSheetHeader`, `isSheetTitle`, `isSheetDescription`, `isSheetFooter`, `isSheetClose` |
| Bulle | `hasPopover` garde dans la ligne ce qui l'ouvre ; `isPopover` est le panneau |
| Infobulle | `isTooltipWrapper`, la bulle nue qui contient le `isTooltip` ; `isTooltipArrow`, dessinée sur le bord qui fait face au déclencheur une fois que le contrôleur a écrit `isTop`, `isBottom`, `isStart` ou `isEnd` sur la bulle |
| Menu déroulant | `isDropdownItem` avec `isDanger` ou `isInset`, `isDropdownIndicator`, `isDropdownLabel`, `isDropdownSeparator`, `isDropdownGroup`, `isDropdownShortcut`, `isDropdownSub`, `isDropdownSubIcon` |
| Liste de choix | `isSelectContent`, sur un `isDropdownMenu` ; `isSelectTrigger`, `isSelectValue`, `isSelectIcon`, `isSelectViewport`, `isSelectScroll`, `isSelectGroup`, `isSelectLabel`, `isSelectItem`, `isSelectItemText`, `isSelectIndicator`, `isSelectSeparator` |
| Fil d'Ariane | `isBreadcrumbList`, `isBreadcrumbItem`, `isBreadcrumbLink`, `isBreadcrumbPage`, `isBreadcrumbEllipsis` ; `isBreadcrumbSeparator`, un élément qui remplace le séparateur dessiné |
| Pagination | `isPaginationList`, `isPaginationItem`, `isPaginationLink`, `isPaginationEllipsis` |
| Carrousel à boutons | `isCarouselFrame` autour de `isCarouselViewport`, qui défile, `isCarouselTrack` et `isCarouselSlide` ; `isCarouselPrevious`, `isCarouselNext`, `isCarouselToggle` ; `isVertical`, `hasCenteredSlides` ou `hasEndSlides` sur le cadre |
| Tableau | `isTable`, `isTableCaption` sous lui, `isTableHeader`, `isTableBody`, `isTableFooter`, `isTableRow` éclairée par `aria-selected`, `isTableHead`, `isTableCell` |
| Rapport d'aspect | `isAspectRatio`, tenu à `--phil-aspect-ratio`, son image ou sa vidéo le couvrant |
| Repliable | `isCollapsible`, un `details` sans cadre ; `isCollapsibleTrigger`, `isCollapsibleContent` |
| Touches | `isKbdGroup`, les éléments `kbd` d'un raccourci côte à côte ; chaque touche est habillée par la base sans classe |
| Champ | `isField`, empilé, ou côte à côte avec `isHorizontal`, ou avec `isResponsive` sur un conteneur de 40rem ou plus ; `isDanger` sur lui colore les bordures de son contrôle ; `isFieldLabel`, `isFieldDescription`, `isFieldError`, `isFieldSet`, `isFieldLegend`, `isFieldGroup`, `isFieldContent`, `isFieldTitle`, `isFieldSeparator` avec une ligne de chaque côté de son texte |
| Élément de liste | `isItem`, avec `isOutline`, `isMuted` ou `isSmall`, éclairé sous le pointeur quand c'est un lien ; `isItemMedia` avec `hasIcon` ou `hasImage`, `isItemContent`, `isItemTitle`, `isItemDescription`, `isItemActions`, `isItemHeader`, `isItemFooter`, `isItemGroup`, `isItemSeparator` |
| Zone de défilement | `isScrollArea`, des barres de défilement fines, un anneau de focus quand le clavier la fait défiler ; `isVertical` ou `isHorizontal` la tient à un axe |
| Bouton à bascule | `isToggle`, avec `isOutline`, `isSmall` ou `isLarge` : un libellé enfoncé quand son `isToggleInput` est coché, son anneau de focus autour de lui ; `isToggleGroup` |
| Groupe de boutons radio | `isRadioGroup`, chaque bouton radio à côté de son libellé ; en rangée qui passe à la ligne avec `isHorizontal` |
| Connexion et inscription | `isLoginForm`, `isSignupForm`, sur un `isCard` d'une largeur de lecture, le bouton de soumission d'un bord à l'autre ; `isLoginFormForgot` |

## Utilitaires

| Classe | Ce qu'elle fait |
|---|---|
| `isVisuallyHidden` | Lu par les lecteurs d'écran, pas affiché |
| `isSkipLink` | Caché jusqu'à ce qu'il ait le focus : le lien qui saute la navigation |
| `isMuted` | La couleur du texte atténué |
| `isGrow` | Grandit pour prendre l'espace qui reste dans un conteneur flex |

## Les recettes propres au kit

Chaque recette écrit l'une de ces classes sur chacune de ses parties.

| Recette | Classes |
|---|---|
| Bouton de copie | `isCopyButton`, `isCopyButtonTrigger` |
| Bouton animé | `isAnimatedButton` avec `hasShine`, `hasLift` ou `hasArrow` ; `isAnimatedButtonLabel`, `isAnimatedButtonArrow` |
| Arbre | `isTree`, `isTreeItem`, `isTreeRow`, `isTreeToggle`, `isTreeLabel`, `isTreeGroup` |
| Note | `isRating`, avec `isReadonly` pour la note seule ; `isRatingLegend`, `isRatingStars`, `isRatingStar`, avec `isFilled` ou `isHalf` en lecture seule ; `isRatingIcon`, `isRatingClear` |
| Champ numérique | `isNumberInput`, `isNumberInputField`, `isNumberInputStep` |
| Champ de fichiers | `isFileInput`, `isFileInputZone`, avec `isDragging` pendant un glisser ; `isFileInputField`, `isFileInputLabel`, `isFileInputHint`, `isFileInputList`, `isFileInputItem`, `isFileInputError`, `isFileInputClear` |
| Étiquette | `isTag`, sur laquelle une tonalité s'applique ; `isTagLabel`, `isTagRemove` |
| Temps relatif | `isRelativeTime` |
| Anneau de progression | `isProgressRing`, avec `isIndeterminate` sans valeur ; `isProgressRingImage`, `isProgressRingTrack`, `isProgressRingValue`, `isProgressRingText` |
| Courbe miniature | `isSparkline`, `isSparklineLine`, `isSparklineArea`, `isSparklineDot` |
| Graphique | `isChart`, `isChartCaption`, `isChartImage`, `isChartGrid`, `isChartAxis`, `isChartTick`, `isChartSeries` et `isChartBar`, `isChartLine`, `isChartLegend`, `isChartSwatch`, `isChartTable`, `isChartData` ; `hasChartJs` et `isChartCanvas` autour du canevas de Chart.js. Chaque série, sa ligne et sa pastille prennent une tonalité — `isPrimary`, `isInfo`, `isSuccess`, `isWarning`, `isDanger`, `isNeutral` — et la ligne de chaque tonalité sauf `isPrimary` un tiret à elle |
| Avant-après | `isBeforeAfter`, `isBeforeAfterAfter`, `isBeforeAfterBefore`, `isBeforeAfterRange` ; la coupe se tient à `--phil-before-after-position` |
| Sélecteur de couleur | `isColorPicker`, `isColorPickerWell`, `isColorPickerValue`, `isColorPickerSwatches`, `isColorPickerSwatch` |
| Sélecteur de date | `isDatePicker`, `isDatePickerField`, `isDatePickerTrigger`, `isDatePickerPanel`, `isDatePickerHeader`, `isDatePickerHeading`, `isDatePickerMonth`, `isDatePickerGrid`, `isDatePickerWeekday`, `isDatePickerDay` |
