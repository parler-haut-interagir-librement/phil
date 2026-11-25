# Celebration

A celebration for a victory: a message said in a polite live region, and an effect drawn over the page on a canvas, in
the colours of the theme, for a few seconds at most: confetti, fireworks, a laser show, balloons and more. Where motion
is reduced, or where the person asked for none, only the message shows.

```twig {"preview":true}
<twig:Celebration message="You won!">
    <button type="button" data-action="celebration#play">Celebrate</button>
</twig:Celebration>
```

## Installation

::: installation

## Usage

```twig
<twig:Celebration message="{{ 'quiz.right'|trans }}" effect="fireworks" />
```

`message` is what is said when it plays, `effect` one of the effects below, `confetti` by default, and `duration` the run
of the animation in milliseconds, kept between 600 and 4000. Nothing plays until something asks.

| Effect | What is drawn |
|---|---|
| `confetti` | Strips of paper falling from the top |
| `fireworks` | Rings of sparks in the upper half, two bursts a second at most |
| `lasers` | Thin beams rising from the bottom of the screen and swinging slowly, in waves half a second apart, each fading in |
| `balloons` | Balloons rising and leaving by the top; a click bursts one |
| `shooting-stars` | A few trails of light crossing the top in a diagonal, one after the other |
| `streamers` | Ribbons unrolling from the two bottom corners |
| `coins` | Coins falling, spinning and bouncing: a level, a shop, a loot |
| `bubbles` | Soap bubbles floating up and bursting; a click bursts one |
| `aurora` | Three slow veils of colour across the top: a calm, discreet "well done" |
| `spotlight` | The page dimmed around what `focus` names: a winner in a ranking or a draw |
| `trophy` | A cup coming down to where `focus` names, then a burst of sparks: a tournament, a badge |

## Examples

### On a page that arrives on a victory

With `autoplay`, it plays as soon as the page shows it: the page of a result, a podium, a full bingo card. The message is
in the page from the start, and said again once the controller runs.

```twig
<twig:Celebration message="{{ 'raffle.won'|trans }}" :autoplay="true" />
```

### On an event

Any script, or any controller of the page, plays it by dispatching the DOM event `celebrate`, on any element or on the
window; a `detail` of its own may give the `message` and the `effect` of this time.

```js
window.dispatchEvent(new CustomEvent('celebrate', { detail: { message: 'Bingo!', effect: 'fireworks' } }));
```

A Stimulus controller dispatches it without a prefix:

```js
this.dispatch('celebrate', { prefix: false, detail: { message: 'Right answer' } });
```

An event of another controller plays it through an action, without a line of script: here, when a vote is revealed.

```twig
<twig:Celebration message="{{ 'poll.revealed'|trans }}" data-action="poll:revealed@window->celebration#play" />
```

### From the server, without JavaScript

`celebrate()`, a Twig function of the bundle of phil/ux-kit, writes a `<turbo-stream action="celebrate">`, which plays the `Celebration` of the page with its message,
its effect and its focus: in the Turbo Stream response to a form, so that a bundle celebrates a right answer without a
line of script, or in a page.

```twig
{# answer.stream.html.twig #}
<turbo-stream action="replace" target="question">…</turbo-stream>
{{ celebrate('quiz.right'|trans, 'balloons') }}
```

Published on a Mercure topic that a `<turbo-stream-source>` of the page listens to, it plays for every viewer at once.
In PHP, `Phil\UxKit\Application\CelebrationStream` renders the same stream:

```php
$hub->publish(new Update('raffle/42', $stream->render('We have a winner', 'spotlight', '#ticket-1207')));
```

Without Turbo, the streams already in the page when the controller connects are played, then removed. The page needs a
`Celebration` for anything to play, in its layout as a rule.

### By a button

A button inside it plays it by the action `celebration#play`; its params `message` and `effect` stand in for the props.

```twig
<twig:Celebration message="Well done">
    <button type="button" data-action="celebration#play" data-celebration-effect-param="fireworks">Celebrate</button>
</twig:Celebration>
```

### Words in turn

Without a `message`, `messages` is said in turn, one at random each time, never the same twice in a row, so that a
screen reader does not repeat "Bravo" every time. `celebration_messages()`, of the bundle of phil/ux-kit, gives eight words of
praise in the language of the request, English or French; an application gives its own, translated by its translator, for other languages.

```twig
<twig:Celebration :messages="celebration_messages()" effect="aurora" />
```

## The choice of each person

`data-celebrations` on the root, or on any ancestor, carries the choice of the person: `all`, by default, `discreet`,
fewer pieces for a shorter run, or `none`, the message alone. It comes on top of `prefers-reduced-motion` and of
`data-motion="reduce"`, which draw nothing. With phil/settings-bundle, a choice of the `display` section kept for
visitors writes it on the root through `phil_document_attributes()`:

```yaml
# config/packages/phil_settings.yaml
phil_settings:
    settings:
        display.celebrations:
            type: choice
            choices: [all, discreet, none]
            default: all
            section: display
            anonymous: true
            html_attribute: celebrations   # written as data-celebrations
```

A site may also write `data-celebrations="none"` on its root for a day of mourning or of remembrance.

Celebrations also tire: the first three of a session of the tab play in full, the next three with half their pieces,
and the others as discreet ones. The count is kept in the session storage of the browser, or by the page where it
cannot be read.

## Accessibility

The message is said in a polite live region, emptied then written again each time, so that the same words are said
twice; it stays shown. Where motion is reduced, by the system or by `data-motion="reduce"` on an ancestor, nothing is
drawn. There is no sound. The canvas is hidden from screen readers, lets every click through and never holds the page:
the animation lasts a few seconds at most, holds while the tab is hidden, and its canvas is removed at the end, or as
soon as the element leaves the page. One animation runs at a time.

Nothing flashes more than twice a second, under the three flashes of WCAG 2.3.1: fireworks burst twice a second at
most; the laser show draws thin lines at 60 % of their colour at most, never a fill of the screen, its waves half a
second apart, each beam fading in over 300 milliseconds and swinging at half a turn a second at most; the spotlight
dims the page over 400 milliseconds, to 45 % at most.

## Colours and performance

The colours are the `--primary` and `--chart-*` tokens, read where the canvas stands, and read again when the colour scheme or an
attribute of the root changes while it plays, so that a switch to dark mode reaches the pieces already drawn. Where the
browser allows it, the canvas is handed to a worker as an `OffscreenCanvas` and painted off the main thread; the worker
is `celebration_worker.js`, served beside the controller from the same origin, which `worker-src 'self'` allows. A
device of four cores or fewer, of two gigabytes of memory or less, or saving data draws fewer pieces.

## Security

Nothing is written in a `style` attribute and nothing runs inline: the canvas waits in a `template`, and the controller
sets it over the page through the CSSOM, which a strict Content Security Policy allows, `default-src 'self'` without
`'unsafe-inline'` for scripts or styles. The browser suite of the kit plays the celebration under such a policy. On paper, its
canvas is left out.
