# Showing a deck

```twig
{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(talk.source, 'markdown', 'talk-' ~ talk.id), presenter_url: path('phil_slides_presenter', {deck: 'talk-' ~ talk.id})}) }}
```

| Variable | Default | What it does |
|---|---|---|
| `deck` | required | The `Deck`, from `slides_deck()` or `slides_markup()` |
| `presenter_url` | none | A link to the presenter view, opened in a window of its own |
| `id` | `phil-slides` | The `id` of the element |
| `keyboard` | none | `page`: the keys drive the deck wherever the focus is, for a page that holds nothing else |

The speaker's notes are never in this page: the template renders each slide with `slides_content()`, which leaves
them out.

## Without script

The slides follow one another, each a box of the deck's shape with its header, its content and its footer, every
fragment shown: a deck reads in order, prints from the browser one slide per page, and is indexed as a page. The
controls are `hidden`.

## With the controller

`phil--slides--deck` marks the deck `isPresenting` and shows one slide at a time, the `isCurrent` one; the fragments
still to come are `isPending`, kept in their place but hidden. It starts at the slide the address names, `#slide-3`,
and keeps the address on the current slide, so a link or a reload lands on it; a change of the address moves the deck.

| Key | Action |
|---|---|
| Right arrow, Down arrow, Page Down, Space | Next fragment, or next slide |
| Left arrow, Up arrow, Page Up | Previous fragment, or previous slide with all its fragments shown |
| Home, End | First slide, last slide with all its fragments shown |
| F | Full screen on and off |
| Escape | Leaves full screen |

The keys are the deck's while the focus is inside it — the box of the slides takes the focus with Tab —, in full
screen, or anywhere in the page with `keyboard: 'page'`. A key typed in a field, or pressed with Ctrl, Alt or Command,
is left to the page. The buttons *Previous*, *Next* and *Full screen* do the same; the counter says *3 / 12*.

## Accessibility

- The deck is a region named after its title, described as a *slide deck*; each slide is described as a *slide* and
  named *Slide 3 of 12 – its title*.
- A polite live region says each new slide, with its title, once the reader moves; nothing is said when the page
  opens.
- The focus stays where it is: moving through the deck does not take it.
- The entrance of a slide fades only for readers who did not ask for reduced motion.
- *Full screen* is a toggle button, `aria-pressed` saying its state.
- The buttons and the link to the presenter view are the recipe `Button` of the kit: real `button` elements of type
  `button`, and a real link, reached with Tab and pressed with Enter or Space, with the focus ring of the kit.
- The labels are in the translation domain `phil_slides`, in English and French.

## The look

`assets/styles/slides.css` dresses the deck over the PHIL UX stylesheet, with its tokens — `--phil-space-*`,
`--phil-color-*`, `--phil-radius-md`, `--phil-duration-sm` —, else those of the Shadcn kit, else a system colour or an
OKLCH value. The buttons take the look of the kit's `Button`. A slide is a container whose
text grows with its width. The state classes are `isPresenting`, `isCurrent`, `isPending` and `isFullscreen`; the
classes of the directive `class` and of `pageStyle` blocks go on each slide, beside `phil-slide`, for the application's
own styles.

| Element | Class |
|---|---|
| The deck | `phil-slides`, with `data-ratio` |
| The slides' box | `phil-slides__stage` |
| A slide | `phil-slide`, its header `phil-slide__header`, content `phil-slide__content`, footer `phil-slide__footer`, number `phil-slide__number` |
| The controls | `phil-slides__controls`, the counter `phil-slides__counter` |
| The link to the presenter view | `phil-slides__presenter` |
