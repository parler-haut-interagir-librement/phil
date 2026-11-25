# Differences from the PHIL UX kit

This kit holds the recipes the Shadcn kit of the Toolkit does not offer, and a `Carousel` on native scroll snapping
under the props of the PHIL UX kit. Only the markup's classes differ from the PHIL UX kit; a test checks that
everything else is the same for each of them. Another recipe the Shadcn kit already offers is not compared, nor the
`DatePicker` of the PHIL UX kit, whose name the Shadcn kit's own `DatePicker` holds.

| Same in both kits | |
|---|---|
| The recipes and their manifests | `logo`, `animated-headline`, `illustration`, `image`, `gallery`, `lightbox`, `hotspots`, `hero`, `video`, `figure`, `qr-code`, `autosave-status`, `reaction-button`, `reaction-picker`, `coming-soon`, `color-picker`, `copy-button`, `celebration`, `animated-button`, `stepper`, `activity-calendar`, `tree`, `rating`, `number-input`, `file-input`, `tag`, `relative-time`, `progress-ring`, `sparkline`, `before-after`, `carousel`, `login-form`, `signup-form`, `chart`, `chart-js` |
| The props, their types, their defaults | The `{% props %}` declarations are identical |
| The blocks | `content` in `ComingSoon`, `LoginForm` and `SignupForm` |
| The controller of `phil/forms-autosave` | The targets `status`, `notice`, `message`, `useServer`, `discard` and `dismiss`, and the actions of the three buttons, in `AutosaveStatus` |
| The controller of `phil/reactions-bundle` | The targets `summary`, `search`, `status`, `empty`, `group` and `option`, and the actions of the search, in `ReactionPicker` |
| The lightbox's controller | `lightbox_controller.js` is the same file, target `dialog`, actions `open`, `close` and `closeOnClickOutside` |
| The preview's controller | `image_preview_controller.js` is the same file, value `src` |
| The celebration's controller | `celebration_controller.js` and the modules beside it, `celebration_effects.js` and `celebration_worker.js`, are the same files, values `message`, `messages`, `effect`, `autoplay`, `duration`, `colors` and `focus`, targets `message` and `stage`, action `play`, the DOM event `celebrate` and the Turbo Stream action `celebrate` |
| The Stimulus controller | `animated_headline_controller.js` is the same file, values `pause`, `duration`, `loop`, `pauseLabel` and `playLabel`, targets `reveal`, `word` and `toggle`, action `toggle` |
| The accessibility | `alt` required, the headline read whole, nothing moving under reduced motion, the landmarks |

| Differs | PHIL UX | This kit |
|---|---|---|
| Classes | phil-ux's `isSplit`, `isEyebrow`, `isHeadline`, `isReveal`, `isRevealToggle`… | Tailwind utilities on the Shadcn kit's tokens |
| Markers | none | `data-slot` on each component's root, and on the headline's pause button |
| The words' states | Read by phil-ux | Read by arbitrary variants: `[&.isCurrent]:visible`, `[&.isRevealing]:border-current` |
| The caret between two words | Blinks | Hidden: Tailwind has no keyframes of its own for it |
| The image | `isImage` with `hasLayoutFixed`, `hasRatio16x9`, `hasFitCover`, `hasPositionTop`…, a custom ratio in `--phil-image-ratio` | `block h-auto max-w-full` and `data-slot="image"`; `max-w-none` or `w-full` for a layout, `aspect-video`, `aspect-[4/3]`…, `aspect-square`, `aspect-(--image-ratio)` for a ratio, `object-cover`, `object-contain`, `object-top`… for the fit and the crop; `data-slot="picture"` on a `Picture`; `data-slot="image-figure"` and `m-0` on the figure of a caption, `mt-2 text-sm text-muted-foreground` on the caption; the credit, the license and the creation `block`, with `data-slot="image-credit"`, `image-license` and `image-creation`, the license's link `underline underline-offset-4`; the placeholder `bg-muted`, `bg-primary/10`, `bg-blue-100 dark:bg-blue-950`… or `bg-(--image-placeholder)` where phil-ux writes `hasPlaceholderNeutral`… or `hasPlaceholder`; the preview `bg-cover bg-center bg-no-repeat`, `bg-contain` with `fit="contain"`, where it writes `hasPreview`; the description `data-slot="image-description"`, `mt-2 text-sm`, where it writes `isDescription` |
| The gallery | `isGallery`, its columns at least `--phil-gallery-column` wide | `@container` and `data-slot="gallery"` on its root, `grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))]` on its list, the gap `gap-2`, then `@3xl:gap-4`; deferred, `isDeferred` on the root becomes on each `li` `[content-visibility:auto]`, `[contain-intrinsic-size:auto_12rem]`, the ratio of its `data-width` and `data-height` under `supports-[…]` and `-m-[3px] p-[3px]` for the ring of the focus |
| The lightbox | `isLightbox`, `isLightboxTrigger`, `isLightboxDialog`, `isLightboxClose` | `data-slot="lightbox"`, `lightbox-trigger`, `lightbox-dialog` and `lightbox-close`; the dialog `max-w-[calc(100vw-2rem)]` with a `backdrop:bg-black/50`, its image within `max-h-[calc(100dvh-6rem)]`, the hidden words `sr-only`; `forced-colors:border` on the button |
| A caption over its image | `hasOverlayCaption` on the figure, its caption on `--phil-color-veil` | The figure `grid w-fit max-w-full`, its image and its caption in one cell, `[grid-area:1/1]`, the caption `self-end bg-black/70 text-white` |
| The carousel of a gallery | `hasLayoutCarousel`, its markers and buttons drawn by phil-ux | `grid-flow-col auto-cols-[…] snap-x snap-mandatory overflow-x-auto [scroll-marker-group:after]` on the list, `relative` on the root, the markers and buttons by arbitrary variants, `[&::scroll-marker]:…`, `[&::scroll-button(inline-end)]:content-['›'_/_attr(data-next)]` |
| The points of an image | `isHotspots`, `isHotspotsList`, `isHotspot`, `hasHotspotX35`…, read by phil-ux | `data-slot="hotspots"`, `hotspots-list` and `hotspot`; `start-[35%] top-[60%]`… to the nearest five per cent, the exact place by `supports-[…]` on a typed `attr()`, `[direction:ltr]` on the list, `[unicode-bidi:plaintext]` on each link, the list under the image by `max-[36rem]:` |
| The hero | `isHero`, `hasHeroScreen`, `hasHeroLarge`, `hasHeroStart`, `hasHeroCenter`, `isHeroMedia`, `isHeroContent` | `data-slot="hero"`, `hero-media` and `hero-content`; `min-h-svh`, `min-h-[70svh]` or `min-h-80`, `content-end`, `content-start` or `content-center`, the content `bg-black/70 text-white` |
| The video | `isVideo` | `data-slot="video"`, `block h-auto max-w-full`, its ratio from `width` and `height` under `supports-[…]` |
| Several images under one caption | `isFigureGroup` with `hasTwoColumns`, `hasThreeColumns`, `hasFourColumns` | `data-slot="figure-group"`, `grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]`, or `grid-cols-2`, `grid-cols-3`, `grid-cols-4` |
| The illustration's entrance | A slanted clip | `animate-in fade-in slide-in-from-end-8`, under `motion-safe` |
| The colours of a celebration | `--phil-color-brand-fill-loud` and the loud fills of the success, warning, danger and info tones | `--primary`, then the `chart-1` to `chart-5` tokens of the Shadcn kit |
| The canvas of a celebration | `isCelebrationCanvas`, hidden by phil-ux under reduced motion and on paper | `pointer-events-none fixed inset-0 z-50 size-full motion-reduce:hidden in-data-[motion=reduce]:hidden print:hidden` |
| The frame of an image | `hasRounded`, `hasFramed`, `hasRaised`, from the tokens of phil-ux and `--phil-image-radius`, `--phil-image-frame-color`, `--phil-image-shadow` | `rounded-lg`, `border border-border`, `shadow-md` |
| A token | `hasToken`, its ring `--phil-image-token-color` | `aspect-square object-cover rounded-full border-4 border-primary`, in place of the ratio, the fit, the corners and the line |
| Pixel art | `hasPixelated` | `[image-rendering:pixelated]` |
| A view transition | `hasTransition`, the name of `data-transition` or of `--phil-image-transition` | An arbitrary property reading `data-transition` by a typed `attr()` under `supports-[…]`, or `--image-transition` |
| The images on paper | Rules of phil-ux under `@media print`, the height within `--phil-image-print-height` | The `print:` variant on each part: `print:break-inside-avoid`, `print:break-before-avoid`, `print:max-h-[85vh]`, `print:object-contain`, `print:[print-color-adjust:exact]`, the carousel `print:grid-flow-row`, `print:hidden` on the close button |
| The buttons of a carousel right to left | `:dir(rtl)` in phil-ux | `rtl:[&::scroll-button(inline-start)]:content-[…]` and its mirror |
| The buttons of `Carousel` | The `Button` recipe, which its manifest installs | Drawn inline with the classes of the Shadcn kit's button for each `variant` and `size`, the manifest naming no recipe; an unknown variant or size falls back to `outline` and `icon-sm` |
| The layout of `Carousel` | phil-ux | A grid of three columns, the buttons on each side of the viewport, `[scroll-snap-type:inline_mandatory]`, a slide `basis-[var(--carousel-item,100%)]`, a vertical carousel within `--carousel-size` |
| The colours of a chart's series | The tones of phil-ux, `isPrimary` to `isNeutral` | The `chart-1` to `chart-5` tokens of the Shadcn kit, then `muted-foreground` |
| The dashes of a chart's lines | Set by phil-ux on each tone | Arbitrary properties: `[stroke-dasharray:8_4]` |
| Merging a `class` given | Added to the component's own | Added too; conflicting utilities are not merged |
