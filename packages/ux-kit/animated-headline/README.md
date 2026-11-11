# AnimatedHeadline

A headline whose words are revealed through a clip that closes to a caret and opens on the next word. One word is
revealed once, then stands still; several are played through once and stop on the first, within five seconds; with
`loop`, they keep cycling and a pause/play button stops them.

```twig {"preview":true}
<twig:AnimatedHeadline text="We make" :words="['sites', 'apps', 'games']" />
```

## Installation

::: installation

## Usage

```twig
<twig:AnimatedHeadline words="Studio" />
```

## Examples

### Words cycled with a pause button

```twig
<twig:AnimatedHeadline text="Made for" :words="['writers', 'players', 'teams']" :loop="true" :pause="2000"
    :pauseLabel="'animation.pause'|trans" :playLabel="'animation.play'|trans" />
```

### Another level

```twig
<twig:AnimatedHeadline :words="['Soon', 'Bientôt']" :level="2" label="Soon, bientôt" />
```

## Accessibility

- Screen readers read the whole headline at once — the text, the words joined by `separator`, the suffix, or `label`
  —, from a visually hidden copy. The animated words are hidden from them and the clip is `aria-live="off"`: nothing is
  announced while they change.
- Under `prefers-reduced-motion`, nothing moves: the first word stands still, and the controller stops if the reader
  turns the preference on while the page is open. Without JavaScript, the first word stands still too.
- Pause, Stop, Hide (WCAG 2.2.2): without `loop`, the words play through once and stop on the first one, the rest and
  the moves shortened so that the whole run ends within five seconds. With `loop`, the template renders a `<button>`,
  `hidden` until the controller shows it, whose visible text is its name: `pauseLabel` while the words cycle,
  `playLabel` once paused, both translatable, in English by default. The heading is then named by its visually hidden
  copy alone, through `aria-labelledby`, so the button's text is not part of it. One word never cycles, and has no
  button, `loop` or not.
- The cycle also holds while the headline is hovered or holds the focus, and while the document is hidden; a held
  headline shows its current word fully open. Pressing play goes on at once, even under the pointer or the focus.
- A move of the clip ends on `transitionend`, or after `duration` if the event never comes: the clip is never left
  closed.
- The controller `animated-headline` drives the classes `isRevealing`, `isPulsing` and `isCurrent`, and the custom
  property `--phil-reveal-size`, which phil-ux reads; the button is phil-ux's `isRevealToggle`.
