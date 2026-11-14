<!-- phil-dev source-sha256=81b2b9856f78b084793af041f252cff6d1d4b43e4033bdf9cf0990559fceec71 -->
# Différences avec le kit PHIL UX

Ce kit contient les recettes que le kit Shadcn du Toolkit n'offre pas, et un `Carousel` sur l'accrochage de défilement
natif, sous les propriétés du kit PHIL UX. Seules les classes du balisage diffèrent du kit PHIL UX ; un test vérifie que
tout le reste est identique pour chacune d'elles. Une autre recette que le kit Shadcn offre déjà n'est pas comparée, ni
le `DatePicker` du kit PHIL UX, dont le nom est celui du `DatePicker` du kit Shadcn.

| Identique dans les deux kits | |
|---|---|
| Les recettes et leurs manifestes | `logo`, `animated-headline`, `illustration`, `image`, `gallery`, `lightbox`, `hotspots`, `hero`, `video`, `figure`, `qr-code`, `autosave-status`, `reaction-button`, `reaction-picker`, `coming-soon`, `color-picker`, `copy-button`, `celebration`, `animated-button`, `stepper`, `activity-calendar`, `tree`, `rating`, `number-input`, `file-input`, `tag`, `relative-time`, `progress-ring`, `sparkline`, `before-after`, `carousel`, `login-form`, `signup-form`, `chart`, `chart-js` |
| Les propriétés, leurs types, leurs valeurs par défaut | Les déclarations `{% props %}` sont identiques |
| Les blocs | `content` dans `ComingSoon`, `LoginForm` et `SignupForm` |
| Le contrôleur de `phil/forms-autosave` | Les cibles `status`, `notice`, `message`, `useServer`, `discard` et `dismiss`, et les actions des trois boutons, dans `AutosaveStatus` |
| Le contrôleur de `phil/reactions-bundle` | Les cibles `summary`, `search`, `status`, `empty`, `group` et `option`, et les actions de la recherche, dans `ReactionPicker` |
| Le contrôleur de la visionneuse | `lightbox_controller.js` est le même fichier, avec la cible `dialog` et les actions `open`, `close` et `closeOnClickOutside` |
| Le contrôleur de l'aperçu | `image_preview_controller.js` est le même fichier, avec la valeur `src` |
| Le contrôleur de la célébration | `celebration_controller.js` est le même fichier, avec les valeurs `message`, `effect`, `autoplay`, `duration` et `colors`, les cibles `message` et `stage`, l'action `play` et l'événement DOM `celebrate` |
| Le contrôleur Stimulus | `animated_headline_controller.js` est le même fichier, avec les valeurs `pause`, `duration`, `loop`, `pauseLabel` et `playLabel`, les cibles `reveal`, `word` et `toggle`, et l'action `toggle` |
| L'accessibilité | `alt` requis, le titre lu en entier, rien qui bouge sous le mouvement réduit, les repères |

| Diffère | PHIL UX | Ce kit |
|---|---|---|
| Classes | `isSplit`, `isEyebrow`, `isHeadline`, `isReveal`, `isRevealToggle`… de phil-ux | Des utilitaires Tailwind sur les jetons du kit Shadcn |
| Marqueurs | aucun | `data-slot` sur la racine de chaque composant, et sur le bouton de pause du titre |
| Les états des mots | Lus par phil-ux | Lus par des variantes arbitraires : `[&.isCurrent]:visible`, `[&.isRevealing]:border-current` |
| Le curseur entre deux mots | Clignote | Caché : Tailwind n'a pas d'images clés propres pour lui |
| L'image | `isImage` avec `hasLayoutFixed`, `hasRatio16x9`, `hasFitCover`, `hasPositionTop`…, un rapport propre dans `--phil-image-ratio` | `block h-auto max-w-full` et `data-slot="image"` ; `max-w-none` ou `w-full` pour une disposition, `aspect-video`, `aspect-[4/3]`…, `aspect-square`, `aspect-(--image-ratio)` pour un rapport, `object-cover`, `object-contain`, `object-top`… pour l'ajustement et le recadrage ; `data-slot="picture"` sur une `Picture` ; `data-slot="image-figure"` et `m-0` sur la figure d'une légende, `mt-2 text-sm text-muted-foreground` sur la légende ; le crédit, la licence et la création `block`, avec `data-slot="image-credit"`, `image-license` et `image-creation`, le lien de la licence `underline underline-offset-4` ; la couleur d'attente `bg-muted`, `bg-primary/10`, `bg-blue-100 dark:bg-blue-950`… ou `bg-(--image-placeholder)` là où phil-ux écrit `hasPlaceholderNeutral`… ou `hasPlaceholder` ; l'aperçu `bg-cover bg-center bg-no-repeat`, `bg-contain` avec `fit="contain"`, là où il écrit `hasPreview` ; la description `data-slot="image-description"`, `mt-2 text-sm`, là où il écrit `isDescription` |
| La galerie | `isGallery`, ses colonnes larges d'au moins `--phil-gallery-column` | `@container` et `data-slot="gallery"` sur sa racine, `grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))]` sur sa liste, l'espacement `gap-2`, puis `@3xl:gap-4` ; différée, `isDeferred` sur la racine devient sur chaque `li` `[content-visibility:auto]`, `[contain-intrinsic-size:auto_12rem]`, le rapport de ses `data-width` et `data-height` sous `supports-[…]` et `-m-[3px] p-[3px]` pour l'anneau de focus |
| La visionneuse | `isLightbox`, `isLightboxTrigger`, `isLightboxDialog`, `isLightboxClose` | `data-slot="lightbox"`, `lightbox-trigger`, `lightbox-dialog` et `lightbox-close` ; le dialogue `max-w-[calc(100vw-2rem)]` avec un `backdrop:bg-black/50`, son image dans `max-h-[calc(100dvh-6rem)]`, les mots cachés `sr-only` ; `forced-colors:border` sur le bouton |
| Une légende sur son image | `hasOverlayCaption` sur la figure, sa légende sur `--phil-color-veil` | La figure `grid w-fit max-w-full`, son image et sa légende dans une seule cellule, `[grid-area:1/1]`, la légende `self-end bg-black/70 text-white` |
| Le carrousel d'une galerie | `hasLayoutCarousel`, ses marqueurs et ses boutons dessinés par phil-ux | `grid-flow-col auto-cols-[…] snap-x snap-mandatory overflow-x-auto [scroll-marker-group:after]` sur la liste, `relative` sur la racine, les marqueurs et les boutons par des variantes arbitraires, `[&::scroll-marker]:…`, `[&::scroll-button(inline-end)]:content-['›'_/_attr(data-next)]` |
| Les points d'une image | `isHotspots`, `isHotspotsList`, `isHotspot`, `hasHotspotX35`…, lus par phil-ux | `data-slot="hotspots"`, `hotspots-list` et `hotspot` ; `start-[35%] top-[60%]`… aux cinq pour cent près, la place exacte par `supports-[…]` sur un `attr()` typé, `[direction:ltr]` sur la liste, `[unicode-bidi:plaintext]` sur chaque lien, la liste sous l'image par `max-[36rem]:` |
| Le héros | `isHero`, `hasHeroScreen`, `hasHeroLarge`, `hasHeroStart`, `hasHeroCenter`, `isHeroMedia`, `isHeroContent` | `data-slot="hero"`, `hero-media` et `hero-content` ; `min-h-svh`, `min-h-[70svh]` ou `min-h-80`, `content-end`, `content-start` ou `content-center`, le contenu `bg-black/70 text-white` |
| La vidéo | `isVideo` | `data-slot="video"`, `block h-auto max-w-full`, son rapport tiré de `width` et `height` sous `supports-[…]` |
| Plusieurs images sous une légende | `isFigureGroup` avec `hasTwoColumns`, `hasThreeColumns`, `hasFourColumns` | `data-slot="figure-group"`, `grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]`, ou `grid-cols-2`, `grid-cols-3`, `grid-cols-4` |
| L'entrée de l'illustration | Un découpage en biais | `animate-in fade-in slide-in-from-end-8`, sous `motion-safe` |
| Les couleurs d'une célébration | `--phil-color-brand-fill-loud` et les remplissages francs des tonalités de succès, d'avertissement, de danger et d'information | `--primary`, puis les jetons `chart-1` à `chart-5` du kit Shadcn |
| Le canvas d'une célébration | `isCelebrationCanvas`, caché par phil-ux en mouvement réduit et sur papier | `pointer-events-none fixed inset-0 z-50 size-full motion-reduce:hidden in-data-[motion=reduce]:hidden print:hidden` |
| Le cadre d'une image | `hasRounded`, `hasFramed`, `hasRaised`, tirés des jetons de phil-ux et de `--phil-image-radius`, `--phil-image-frame-color`, `--phil-image-shadow` | `rounded-lg`, `border border-border`, `shadow-md` |
| Un jeton | `hasToken`, son anneau `--phil-image-token-color` | `aspect-square object-cover rounded-full border-4 border-primary`, à la place du rapport, de l'ajustement, des coins et de la ligne |
| Le pixel art | `hasPixelated` | `[image-rendering:pixelated]` |
| Une transition de vue | `hasTransition`, le nom de `data-transition` ou de `--phil-image-transition` | Une propriété arbitraire qui lit `data-transition` par un `attr()` typé sous `supports-[…]`, ou `--image-transition` |
| Les images sur papier | Des règles de phil-ux sous `@media print`, la hauteur dans `--phil-image-print-height` | La variante `print:` sur chaque partie : `print:break-inside-avoid`, `print:break-before-avoid`, `print:max-h-[85vh]`, `print:object-contain`, `print:[print-color-adjust:exact]`, le carrousel `print:grid-flow-row`, `print:hidden` sur le bouton de fermeture |
| Les boutons d'un carrousel de droite à gauche | `:dir(rtl)` dans phil-ux | `rtl:[&::scroll-button(inline-start)]:content-[…]` et son miroir |
| Les boutons de `Carousel` | La recette `Button`, que son manifeste installe | Dessinés en ligne avec les classes du bouton du kit Shadcn pour chaque `variant` et `size`, le manifeste ne nommant aucune recette ; une variante ou une taille inconnue retombe sur `outline` et `icon-sm` |
| La disposition de `Carousel` | phil-ux | Une grille de trois colonnes, les boutons de chaque côté de la fenêtre, `[scroll-snap-type:inline_mandatory]`, une diapositive `basis-[var(--carousel-item,100%)]`, un carrousel vertical dans `--carousel-size` |
| Les couleurs des séries d'un graphique | Les tonalités de phil-ux, `isPrimary` à `isNeutral` | Les jetons `chart-1` à `chart-5` du kit Shadcn, puis `muted-foreground` |
| Les tirets des lignes d'un graphique | Posés par phil-ux sur chaque tonalité | Des propriétés arbitraires : `[stroke-dasharray:8_4]` |
| La fusion d'un `class` donné | Ajouté à ceux du composant | Ajouté aussi ; les utilitaires en conflit ne sont pas fusionnés |
