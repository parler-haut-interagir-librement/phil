# Components

What the classless base cannot guess, in the `phil.components` layer, and the utilities, last of all. Each class says
what the element is or has; the recipes of the PHIL UX kit write these names, and only Twig and Stimulus come with them.

## Tones and sizes

A tone is a class that sets six colours — the fill, the border and the colour on that fill, each quiet or loud — on any
component that reads them. It works on a button, a badge and an alert alike, and a button inside a coloured notice keeps
its own.

| Tone | Role of the grid |
|---|---|
| `isPrimary` | brand |
| `isNeutral` | neutral |
| `isDanger` | danger |
| `isSuccess` | success |
| `isWarning` | warning |
| `isInfo` | info |

`isSmall` and `isLarge` set the height of the control (`--phil-control-sm` or `-lg`), its padding and its text size.
They are inherited: on a form or a toolbar, they size every control inside it.

## Buttons

A `button`, a submit input and an element of `role="button"` are solid and in the brand with no class; `isButton` gives
a link or a `summary` the same look. The classes combine: `isDanger isOutline` is a red outline.

| Class | What it does |
|---|---|
| `isButton` | The look of a button, on an element that is not one |
| `isPrimary` | The solid brand, the default said aloud |
| `isSecondary` | The quiet fill of the tone, neutral by default |
| `isGhost` | No fill until the pointer is on it, neutral by default |
| `isOutline` | A loud border and no fill, in the tone of the button |
| `isLink` | No fill and no border, underlined in the colour of links; on a badge too |
| `isDanger` | The danger tone, for what destroys |
| `isSmall`, `isLarge` | A control of 2rem or 3.25rem; the default is the touch target of 2.75rem |
| `isLoading` | A spinner over the content, which keeps its place; no pointer. On any positioned box, buttons hiding their label |
| `hasIcon` | Sizes a child `svg` or `img` at 1.25em; on an `isAlert`, puts it in a column of its own |
| `isIconOnly` | A square button, its name in `aria-label` |

`aria-pressed="true"` and `aria-current` turn a button to the quiet fill with a loud border, whatever its look. A hover
or a press mixes the fill towards the text colour, which raises its contrast with the label. The custom properties
`--phil-button-fill`, `--phil-button-fill-hover`, `--phil-button-fill-active`, `--phil-button-on` and
`--phil-button-border` change one button.

`isButtonGroup` joins buttons side by side, sharing their borders, the focused or pressed one above its neighbours;
`isVertical` stacks them. `isButtonGroupText` is plain text among them, at their height.

## Badges and alerts

`isBadge`: a short status, neutral and quiet by default; `isLoud` makes it solid and `isOutline` keeps only its border.

`isAlert`: a notice in the page, quiet, with a loud rule on its start side and links in its own colour. The template
gives it the `role` it needs, `status` or `alert`. `isLoud` makes it solid; `hasIcon` lays an icon in a column.

```html
<div class="isAlert isWarning hasIcon" role="status">
    <svg aria-hidden="true">…</svg>
    <p>The payment is late.</p>
</div>
```

## Cards and empty states

`isCard`: a surface with a border and a shadow. Its `isCardMedia` image takes the ratio of `--phil-card-media-ratio`
(4 / 3), its `isCardBody` the space left, its `isCardTitle` the title, and a direct `header` and `footer` are ruled off.
An `isCardLink` covers the whole card and moves the focus ring to it.

`isEmptyState`: a dashed frame, a title and what to do about it, centred.

## Tabs

`isTabs` holds an element of `role="tablist"`, its `role="tab"` buttons and `role="tabpanel"` panels; `aria-selected`
marks the tab, and the bar scrolls when it does not fit. The keyboard is the controller's.

## Sheets

`isSheet`, on a `dialog`: a panel against an edge, the end side by default, `isStart`, `isTop` or `isBottom` otherwise,
sliding in where the browser can animate the top layer. `--phil-dialog-size` is its width.

## Switches

`isSwitch`, or `role="switch"` alone, on an `input type="checkbox"`: a track and a thumb, in the brand when checked,
following the writing direction and repainted under forced colours.

## Tooltips

`isTooltip`: the bubble, inverted. As a `popover`, a controller places it. Inside a `hasTooltip` box it shows on hover
and on focus without JavaScript; dismissing it with Escape takes the controller.

## Menus

`isDropdown`, on a `details` or a wrapper, holds an `isDropdownMenu` that opens under its button, or a `popover` that a
controller places. Its links, buttons and `role="menuitem"` are full-width targets; an `hr` separates them.

```html
<details class="isDropdown">
    <summary class="isButton isSecondary">Actions</summary>
    <ul class="isDropdownMenu"><li><a href="/edit">Edit</a></li></ul>
</details>
```

## Accordions

`isAccordion` holds `details` that read as one, ruled off from each other. A shared `name` on them keeps one open at a
time, natively.

## Paths through the pages

`isBreadcrumb`, on a `nav` holding a list: a separator between the items, drawn and hidden from screen readers.

`isPagination`, on a `nav` holding a list of links or buttons, each a touch target; `aria-current` marks the page and
`aria-disabled="true"` a link that leads nowhere.

`isStepper`, on an `ol`: numbered steps joined by a line, the current one `aria-current="step"`, the finished ones
`isDone`; they read downwards on a phone. `isStepperItem` and `isStepperLabel` are the parts of a step.

## Waiting and placeholders

`isSpinner`: a ring the size of the text around it; give it `role="status"` and a name. `isSkeleton`: the pulsing place
of something still loading. Under reduced motion both stand still.

## Avatars, separators, carousels

`isAvatar`: a round picture, or initials on the quiet neutral, at the size of a control, so `isSmall` and `isLarge`
apply.

`isSeparator`: a rule, on an `hr`, or on both sides of a short label on a `div`; `isVertical` stands up inside a row.

`isCarousel`: slides in a row, snapped, each of width `--phil-carousel-item` (100 %), with `--phil-carousel-gap` between
them. The template makes it focusable, so the keyboard can scroll it.

## The activity calendar

`isActivityCalendar`: a cell per day, seven rows, as many columns as there are weeks. Each child says its level with
`data-level`, from 0 to 4, and its meaning with a title or a label: two levels apart reach 3:1, so the colour never
stands alone. `isActivityCalendarDay` is a day and `isActivityCalendarBlank` an empty cell before the first one;
`hasActivityCalendar` holds the calendar and `isActivityLegend`, the five levels between two words.

## Tables

`isStriped` stripes the rows of a table, `hasHover` lights the one under the pointer, and `isScrollable` is the box that
scrolls a table too wide for the page, when a `figure` will not do.

## QR codes and highlighted code

`isQrCode` holds the drawing and its `isQrCodeCaption`.

The `hl-*` classes a highlighter writes (`hl-keyword`, `hl-string`, `hl-number`, `hl-comment`, `hl-function`, `hl-tag` and
their kin) each take a colour role read on a surface, through `--phil-hl-keyword`, `--phil-hl-string`, `--phil-hl-number`,
`--phil-hl-comment`, `--phil-hl-name` and `--phil-hl-markup`.

## UJO media on paper

UJO (Universal JSON Output) renders a frame, a video or a sound followed by `.ujo-print-fallback`: its poster, its title,
its address and a QR code. The stylesheet hides that fallback everywhere but on paper, and on paper shows it in place of
the player.

## The eyebrow

`isEyebrow`: a short label above a heading — "Coming soon", a category, a date —, small, muted and never a heading
of its own. With its heading in an `hgroup`, it reads as part of the title.

```html
<hgroup>
    <p class="isEyebrow">Coming soon</p>
    <h1 class="isHeadline">Studio</h1>
</hgroup>
```

## The headline and its clip reveal

`isHeadline`: a display heading, fluid from the `2xl` to the `4xl` text size.

`isReveal`: the words of a headline stacked in one box, the one marked `isCurrent` shown. Without JavaScript, it
stands still. The `animated-headline` controller of the PHIL UX kit drives it:

| State or property | Set by | Effect |
|---|---|---|
| `isRevealing` | The controller, while it drives the clip | The caret shows on the end side |
| `isPulsing` | The controller, while a word rests between two moves | The caret blinks |
| `isCurrent` | The template on the first word, then the controller | The word shown |
| `--phil-reveal-size` | The controller | The clip's width, transitioned |
| `--phil-reveal-duration` | The controller, from its value | The transition's duration |
| `--phil-reveal-caret` | The stylesheet, 0.06em | The caret's width |

Once the words have played through, the controller removes `isRevealing`, `isPulsing` and `--phil-reveal-size`: the
clip is back to its natural width. `isRevealToggle` is the pause/play button of a cycling headline: small text in the
heading's line, on the raised surface with a border, hidden while it has the `hidden` attribute.

Under `prefers-reduced-motion`, the clip keeps its natural width, the caret is hidden and nothing transitions. The
animated words are meant to be `aria-hidden`, with the whole headline in an `isVisuallyHidden` copy.

```html
<h1 class="isHeadline">
    <span class="isVisuallyHidden">We make sites, apps, games</span>
    <span aria-hidden="true">We make <span class="isReveal" aria-live="off">
        <span class="isCurrent">sites</span><span>apps</span><span>games</span>
    </span></span>
</h1>
```

## The logo

`isLogo`: an inline block whose image is `--phil-logo-size` high (3rem), its width from its ratio.

## The image

`isImage`: an image of the content as a block, never wider than its container, its height following its width, so
that the `width` and `height` written on it reserve its space at its ratio before it loads without stretching it
once the container is narrower.

With it, on the same element, never in a `style` attribute:

| Class | What it does |
|---|---|
| `hasLayoutFixed` | At its width whatever the container, `max-inline-size: none` |
| `hasLayoutFullWidth` | Across the whole container, `inline-size: 100%` |
| `hasRatio16x9`, `hasRatio4x3`, `hasRatio3x2`, `hasRatio1x1`, `hasRatio21x9`, `hasRatio3x4`, `hasRatio2x3`, `hasRatio9x16` | The ratio of its box |
| `hasRatio` | The ratio of `--phil-image-ratio`, which a class of the site sets |
| `hasFitCover`, `hasFitContain` | How the picture fills its box: cropped to cover it, or whole inside it |
| `hasPositionCenter`, `hasPositionTop`, `hasPositionBottom`, `hasPositionLeft`, `hasPositionRight` | Where the picture is cropped |

A ratio of its own comes from a class of the site's stylesheet, which a nonce-based Content Security Policy keeps,
where an inline `style` would be dropped:

```css
.isHeroPhoto {
    --phil-image-ratio: 5 / 2;
}
```

```html
<img class="isImage hasRatio hasFitCover isHeroPhoto" src="/hall.jpg" alt="The hall" width="1500" height="1000">
```

Under a caption, `isCredit`, `isLicense` and `isCreation` each take a line of their own: who made the image, its
license, and how it was made.

```html
<figure>
    <img class="isImage" src="/bridge.jpg" alt="A bridge" width="800" height="600">
    <figcaption>The bridge at dusk<small class="isCredit">Photo: Ana Lima</small><small class="isLicense"><a href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a></small></figcaption>
</figure>
```

While the image loads, a placeholder paints its box in a quiet tone of the tokens, near the dominant colour of the
picture: `hasPlaceholderNeutral`, `hasPlaceholderBrand`, `hasPlaceholderRed`, `hasPlaceholderGreen`,
`hasPlaceholderAmber`, `hasPlaceholderBlue` or `hasPlaceholderViolet`, each a `--phil-color-placeholder-*` role in
clear and in dark; `hasPlaceholder` reads `--phil-image-placeholder`, set by a class of the site. The tone stays behind
a transparent picture once it has loaded: give it to photographs, not to logos.

`hasPreview` lays out the blurred preview the `image-preview` controller of the kit paints behind the image while it
loads, as the picture will be, covering its box or, with `hasFitContain`, inside it. The controller sets the background
through the CSSOM, which a strict Content Security Policy allows, where a `style` attribute would be dropped.

`isDescription`, a disclosure under the image and before its caption, holds its long description; the image is
described by the paragraph inside.

```html
<figure>
    <img class="isImage hasPlaceholderBlue" src="/chart.png" alt="Visits by month" width="800" height="600" aria-describedby="visits-description">
    <details class="isDescription"><summary>Description</summary><p id="visits-description">Visits rose from 12 in January to 30 in February.</p></details>
</figure>
```

`hasOverlayCaption`, on the figure, lays its caption over the foot of the image, on `--phil-color-veil`, its text in
`--phil-color-on-veil`, which reaches 9:1 over a white picture; the figure is as wide as its image, a description stays
under both.

```html
<figure class="hasOverlayCaption">
    <img class="isImage" src="/hall.jpg" alt="The main hall" width="960" height="640">
    <figcaption>The main hall</figcaption>
</figure>
```

## The gallery

`isGallery`: a container whose list lays its figures in a grid of columns `--phil-gallery-column` wide at least (12rem),
as many as its own width holds, read by a container query rather than the width of the screen; the gap,
`--phil-gallery-gap`, grows once the gallery is 48rem wide. Each image fills its cell.

```html
<div class="isGallery">
    <ul role="list" aria-label="The festival">
        <li><img class="isImage hasRatio1x1 hasFitCover" src="/stage.jpg" alt="The stage" width="800" height="600"></li>
    </ul>
</div>
```

`isDeferred`, on a long gallery, lets the browser skip the rendering of the figures off screen with
`content-visibility: auto`. Each `li` is kept meanwhile at the ratio of its `data-width` and `data-height`, which
`attr()` reads where the browser types it, Chromium 133 and later; elsewhere it is a square cell `--phil-gallery-column`
high, until it has been rendered once, after which its size is remembered. Neither counts the caption, which shows once
the figure is rendered. Each cell reaches into the gap by the width of the focus ring, which its paint containment would
otherwise clip.

```html
<div class="isGallery isDeferred">
    <ul role="list" aria-label="The archive">
        <li data-width="4" data-height="3"><img class="isImage hasRatio4x3 hasFitCover" src="/stage.jpg" alt="The stage" width="800" height="600" loading="lazy" fetchpriority="low"></li>
    </ul>
</div>
```

`hasLayoutCarousel` lays the figures in one row that scrolls sideways and snaps on each, the keyboard scrolling it once
the list has the focus. Where the browser draws them, Chromium 135 and later, `::scroll-marker` gives each `li` a
marker under the row, named by its `data-marker`, and `::scroll-button()` a button on each side, half way down the row,
named by the `data-previous` and `data-next` of the list; elsewhere the row scrolls alone.

```html
<div class="isGallery hasLayoutCarousel">
    <ul role="list" aria-label="The strip" tabindex="0" data-previous="Previous" data-next="Next">
        <li data-marker="The stage"><img class="isImage hasRatio1x1 hasFitCover" src="/stage.jpg" alt="The stage" width="800" height="600"></li>
    </ul>
</div>
```

## The lightbox

`isLightbox` holds `isLightboxTrigger`, the thumbnail's button, without a fill, with the focus ring and a zoom cursor,
and `isLightboxDialog`, a dialog as wide as the screen allows whose image is shown whole within its height; its close
button, `isLightboxClose`, sits in its end corner. Where colours are forced, the thumbnail's button keeps the frame of
a system button.

## Points over an image

`isHotspots` holds an image and an `isHotspotsList`, each point an `isHotspot` laid over the image at its point, its
link a pill of `--phil-color-on-veil` text on `--phil-color-veil`, 24 pixels high at least. The place comes from
`hasHotspotX` and `hasHotspotY` followed by a multiple of five, `hasHotspotX0` to `hasHotspotX100`, which set
`--phil-hotspot-x` and `--phil-hotspot-y`; where the browser types `attr()`, Chromium 133 and later, the `data-x` and
`data-y` of the point, percentages without their sign, place it exactly. The place is from the left and from the top,
the same right to left: the list is laid out left to right, each link reading its words in their own direction. Under
36rem, the points read as a list under the image.

```html
<div class="isHotspots">
    <img class="isImage" src="/plan.png" alt="The plan of the hall" width="800" height="400">
    <ul role="list" class="isHotspotsList" aria-label="Rooms">
        <li class="isHotspot hasHotspotX75 hasHotspotY65" data-x="72.5" data-y="64"><a href="/bar">Bar</a></li>
    </ul>
</div>
```

## The hero

`isHero` stands 20rem high at least, `hasHeroScreen` the height of the screen, `hasHeroLarge` seven tenths of it; its
`isHeroMedia` lays the image under the whole box, covering it, and its `isHeroContent` lies over it on
`--phil-color-veil`, at the end of the height, or at its start with `hasHeroStart`, in its centre with `hasHeroCenter`.

## The video

`isVideo`: a video as wide as its width allows, never wider than its container, kept at the ratio of its `width` and
`height` while nothing is loaded.

## Several images under one caption

`isFigureGroup`: a grid of columns `--phil-figure-column` wide at least (10rem), as many as the container holds, or
two, three or four on every screen with `hasTwoColumns`, `hasThreeColumns` and `hasFourColumns`; each image fills its cell.

## Reactions

`isReaction`: one reaction, a target 2.75rem wide and tall whatever it holds, marked when its `aria-pressed` is true;
the recipes of the kit use it on a button, a summary or plain text. `isReactionPicker` holds the other reactions, and
`isReactionPickerPanel` lays them over what follows.

## Prose

`isProse`: long text written by people — a readable measure, the same gap between blocks (`--phil-prose-gap`), more
room before the headings, rounded media.

## The parts of the components

Every part of a component has its class, so that a page written by hand and the recipes of the PHIL UX kit read the
same. None of these rules looks at a data attribute of the recipes.

| Component | Parts |
|---|---|
| Alert | `isAlertTitle`, `isAlertDescription`, `isAlertAction` |
| Avatar | `isAvatarImage`, `isAvatarFallback`, `isAvatarBadge`, on the edge of the picture; `isAvatarGroup` overlaps them, `isAvatarGroupCount` counts those left out |
| Card | `isCardHeader`, `isCardBody`, `isCardTitle`, `isCardDescription`, `isCardFooter`, `isCardAction` at the end of the header; `isSmall` on the card tightens them |
| Empty state | `isEmptyHeader`, `isEmptyMedia`, `isEmptyTitle`, `isEmptyDescription`, `isEmptyContent` |
| Tabs | `isTabList`, `isTab`, `isTabPanel`; on the panel, `isCurrent` says which one shows while a script is yet to run; `isVertical` on the `isTabs` |
| Accordion | `isAccordionItem`, `isAccordionTrigger`, `isAccordionContent` |
| Dialog | `hasDialog` keeps what opens it in the line; `isDialog`, `isDialogHeader`, `isDialogTitle`, `isDialogDescription`, `isDialogFooter`, `isDialogClose` in the corner |
| Sheet | `isSheetHeader`, `isSheetTitle`, `isSheetDescription`, `isSheetFooter`, `isSheetClose` |
| Popover | `hasPopover` keeps what opens it in the line; `isPopover` is the panel |
| Tooltip | `isTooltipWrapper`, the bare popover that holds the `isTooltip`; `isTooltipArrow`, drawn on the edge facing the trigger once the controller has written `isTop`, `isBottom`, `isStart` or `isEnd` on the bubble |
| Dropdown menu | `isDropdownItem` with `isDanger` or `isInset`, `isDropdownIndicator`, `isDropdownLabel`, `isDropdownSeparator`, `isDropdownGroup`, `isDropdownShortcut`, `isDropdownSub`, `isDropdownSubIcon` |
| Select | `isSelectContent`, on an `isDropdownMenu`; `isSelectTrigger`, `isSelectValue`, `isSelectIcon`, `isSelectViewport`, `isSelectScroll`, `isSelectGroup`, `isSelectLabel`, `isSelectItem`, `isSelectItemText`, `isSelectIndicator`, `isSelectSeparator` |
| Breadcrumb | `isBreadcrumbList`, `isBreadcrumbItem`, `isBreadcrumbLink`, `isBreadcrumbPage`, `isBreadcrumbEllipsis`; `isBreadcrumbSeparator`, an item that replaces the drawn separator |
| Pagination | `isPaginationList`, `isPaginationItem`, `isPaginationLink`, `isPaginationEllipsis` |
| Carousel with buttons | `isCarouselFrame` around `isCarouselViewport`, which scrolls, `isCarouselTrack` and `isCarouselSlide`; `isCarouselPrevious`, `isCarouselNext`, `isCarouselToggle`; `isVertical`, `hasCenteredSlides` or `hasEndSlides` on the frame |
| Table | `isTable`, `isTableCaption` under it, `isTableHeader`, `isTableBody`, `isTableFooter`, `isTableRow` lit by `aria-selected`, `isTableHead`, `isTableCell` |
| Aspect ratio | `isAspectRatio`, kept to `--phil-aspect-ratio`, its picture or video covering it |
| Collapsible | `isCollapsible`, a `details` without a frame; `isCollapsibleTrigger`, `isCollapsibleContent` |
| Keys | `isKbdGroup`, the `kbd` elements of one shortcut side by side; each key is dressed by the classless base |
| Field | `isField`, stacked, or side by side with `isHorizontal`, or with `isResponsive` on a container of 40rem or wider; `isDanger` on it colours the borders of its control; `isFieldLabel`, `isFieldDescription`, `isFieldError`, `isFieldSet`, `isFieldLegend`, `isFieldGroup`, `isFieldContent`, `isFieldTitle`, `isFieldSeparator` with a line on each side of its text |
| Item | `isItem`, with `isOutline`, `isMuted` or `isSmall`, lit under the pointer when it is a link; `isItemMedia` with `hasIcon` or `hasImage`, `isItemContent`, `isItemTitle`, `isItemDescription`, `isItemActions`, `isItemHeader`, `isItemFooter`, `isItemGroup`, `isItemSeparator` |
| Scroll area | `isScrollArea`, thin scroll bars, a focus ring when the keyboard scrolls it; `isVertical` or `isHorizontal` keeps it to one axis |
| Toggle | `isToggle`, with `isOutline`, `isSmall` or `isLarge`: a label pressed when its `isToggleInput` is checked, its focus ring around it; `isToggleGroup` |
| Radio group | `isRadioGroup`, each radio button beside its label; in a row that wraps with `isHorizontal` |
| Sign-in and sign-up | `isLoginForm`, `isSignupForm`, on an `isCard` of a reading width, the submit button across it; `isLoginFormForgot` |

## Utilities

| Class | What it does |
|---|---|
| `isVisuallyHidden` | Read by screen readers, not shown |
| `isSkipLink` | Hidden until it has the focus: the link past the navigation |
| `isMuted` | The muted text colour |
| `isGrow` | Grows to take the space left in a flex container |

## Recipes of the kit of its own

Each recipe writes one of these classes on each of its parts.

| Recipe | Classes |
|---|---|
| Copy button | `isCopyButton`, `isCopyButtonTrigger` |
| Animated button | `isAnimatedButton` with `hasShine`, `hasLift` or `hasArrow`; `isAnimatedButtonLabel`, `isAnimatedButtonArrow` |
| Tree | `isTree`, `isTreeItem`, `isTreeRow`, `isTreeToggle`, `isTreeLabel`, `isTreeGroup` |
| Rating | `isRating`, with `isReadonly` for the note alone; `isRatingLegend`, `isRatingStars`, `isRatingStar`, with `isFilled` or `isHalf` when read-only; `isRatingIcon`, `isRatingClear` |
| Number input | `isNumberInput`, `isNumberInputField`, `isNumberInputStep` |
| File input | `isFileInput`, `isFileInputZone`, with `isDragging` under a drag; `isFileInputField`, `isFileInputLabel`, `isFileInputHint`, `isFileInputList`, `isFileInputItem`, `isFileInputError`, `isFileInputClear` |
| Tag | `isTag`, on which a tone applies; `isTagLabel`, `isTagRemove` |
| Relative time | `isRelativeTime` |
| Progress ring | `isProgressRing`, with `isIndeterminate` without a value; `isProgressRingImage`, `isProgressRingTrack`, `isProgressRingValue`, `isProgressRingText` |
| Sparkline | `isSparkline`, `isSparklineLine`, `isSparklineArea`, `isSparklineDot` |
| Chart | `isChart`, `isChartCaption`, `isChartImage`, `isChartGrid`, `isChartAxis`, `isChartTick`, `isChartSeries` and `isChartBar`, `isChartLine`, `isChartLegend`, `isChartSwatch`, `isChartTable`, `isChartData`; `hasChartJs` and `isChartCanvas` around the canvas of Chart.js. Each series, its line and its swatch take a tone — `isPrimary`, `isInfo`, `isSuccess`, `isWarning`, `isDanger`, `isNeutral` — and the line of each tone but `isPrimary` a dash of its own |
| BeforeAfter | `isBeforeAfter`, `isBeforeAfterAfter`, `isBeforeAfterBefore`, `isBeforeAfterRange`; the cut stands at `--phil-before-after-position` |
| Colour picker | `isColorPicker`, `isColorPickerWell`, `isColorPickerValue`, `isColorPickerSwatches`, `isColorPickerSwatch` |
| Date picker | `isDatePicker`, `isDatePickerField`, `isDatePickerTrigger`, `isDatePickerPanel`, `isDatePickerHeader`, `isDatePickerHeading`, `isDatePickerMonth`, `isDatePickerGrid`, `isDatePickerWeekday`, `isDatePickerDay` |
