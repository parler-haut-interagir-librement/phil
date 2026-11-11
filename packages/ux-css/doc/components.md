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
