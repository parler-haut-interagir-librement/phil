# Celebration

A celebration for a victory: a message said in a polite live region, and an effect drawn over the page on a canvas, in
the colours of the theme, for a few seconds at most: confetti, fireworks, a laser show, balloons, a wave through the
page, a drum roll and more. Where motion is reduced, or where the person asked for none, only the message shows. Its
sounds stay silent until the person turns them on, and a device vibrates only when the page asks.

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
of the animation in milliseconds, kept between 600 and 4000. Nothing plays until something asks. `focus` is the
selector of what some effects aim at, `reaction` what rains with `reactions`, `shapes` and `mascot` the images of the
site, `vibrate` turns on a short vibration, and `sounds` adds the button of the sounds, labelled by `soundLabel`.

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
| `wave` | The children of what `focus` names, or of the `main` element, lifted one after the other like a wave in a stadium; nothing is drawn |
| `rocket` | A rocket taking off from under what `focus` names, a progress bar at 100 % as a rule, faster and faster, a trail of sparks behind |
| `rainbow` | A rainbow drawn from one edge to the other, one band of each colour of the theme |
| `drum-roll` | A drum whose sticks strike for most of the run, then sparks on what `focus` names; the message is said when the roll ends |
| `applause` | Pairs of hands clapping along the bottom |
| `reactions` | The reaction of `reaction`, an emoji or a short word, raining: 🎉 without one |
| `shapes` | Confetti in the shapes of the site, the images of `shapes`, a logo or a mascot; strips of paper without them |
| `mascot` | The mascot of the site, the image of `mascot`, hopping across the bottom; a little figure in a pointed hat without one |

## Examples

### Every effect

One button for each effect; nothing plays until one is pressed. Where motion is reduced, or where the person asked for
none, the message alone is said; no sound plays here, since this gallery has no button of the sounds.

```twig {"preview":true}
<twig:Celebration message="Well done!">
    <div class="flex flex-wrap gap-2">
        <button type="button" data-action="celebration#play" data-celebration-effect-param="confetti">Confetti</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="fireworks">Fireworks</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="lasers">Laser show</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="balloons">Balloons</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="shooting-stars">Shooting stars</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="streamers">Streamers</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="coins">Coins</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="bubbles">Bubbles</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="aurora">Aurora</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="spotlight">Spotlight</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="trophy">Trophy</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="wave">Wave</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="rocket">Rocket</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="rainbow">Rainbow</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="drum-roll">Drum roll</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="applause">Applause</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="reactions">Reactions</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="shapes">Shapes</button>
        <button type="button" data-action="celebration#play" data-celebration-effect-param="mascot">Mascot</button>
    </div>
</twig:Celebration>
```

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

```twig {"preview":true}
<twig:Celebration message="Well done">
    <button type="button" data-action="celebration#play" data-celebration-effect-param="fireworks">Celebrate</button>
</twig:Celebration>
```

### A wave through a list

The wave lifts the children of what `focus` names, here the items of a list, one after the other, then sets them down;
without a `focus`, those of the `main` element of the page. It moves them by the Web Animations API, added to their own
transforms, at most 24 of them, and draws nothing.

```twig {"preview":true}
<ul id="standings">
    <li>Ada</li>
    <li>Grace</li>
    <li>Linus</li>
</ul>
<twig:Celebration message="The whole room cheers" effect="wave" focus="#standings">
    <button type="button" data-action="celebration#play">Start the wave</button>
</twig:Celebration>
```

### A drum roll before the winner

The drum roll keeps its words for the end of its roll, then bursts into sparks on what `focus` names: the winner of a
draw, revealed by the server. Where motion is reduced, the words are said at once.

```twig
<twig:Celebration message="{{ 'raffle.winner'|trans({'%name%': winner.name}) }}" effect="drum-roll" focus="#ticket-{{ winner.ticket }}" :autoplay="true" :duration="4000" />
```

### A rocket at 100 %

When a progress reaches its end, the script that fills it plays the rocket from under the bar, its `focus` an element
or a selector:

```js
window.dispatchEvent(new CustomEvent('celebrate', { detail: { effect: 'rocket', focus: '#course-progress' } }));
```

### A rain of reactions

`reaction` is what rains, an emoji or a short word, eight characters at most; the param `reaction` of a button, or the
`reaction` of the `detail` of the event, stands in for it. A `ReactionButton` takes the same attributes, so that a
reaction sent also rains.

```twig {"preview":true}
<twig:Celebration message="Thanks for the love" effect="reactions" reaction="❤️">
    <div class="flex flex-wrap gap-2">
        <button type="button" data-action="celebration#play" data-celebration-reaction-param="👏">Applaud</button>
        <button type="button" data-action="celebration#play" data-celebration-reaction-param="❤️">Love it</button>
    </div>
</twig:Celebration>
```

`celebrate()` takes it as its fourth argument, so that every viewer of a Mercure topic sees the reaction rain at once:
`{{ celebrate(effect: 'reactions', reaction: '🎉') }}`.

### The shapes and the mascot of the site

`shapes` lists the images confetti fall in, the logo of the site or its emblems, eight at most; `mascot` is the image
that hops across the bottom of the screen. Both are loaded once, as soon as the celebration connects, and drawn into
bitmaps of 128 pixels across, which the worker paints; an image that fails to load is left out. SVG and PNG images of
the site itself are best: the `img-src` of the Content Security Policy decides which addresses load.

```twig
<twig:Celebration message="Welcome aboard" effect="shapes" :shapes="[asset('images/logo.svg'), asset('images/star.svg')]" mascot="{{ asset('images/mascot.svg') }}" />
```

### Sounds, if the person wants them

With `sounds`, a button turns the sounds on and off, and says whether they are on by `aria-pressed`; the choice is kept
in the local storage of the browser for the next pages. Silent until then: a celebration never makes a sound the person
did not ask for, nor before a gesture of the person on the page, which a browser asks for before any sound. The sounds
are made on the device by the Web Audio API, without a file: a chime, pops for fireworks, a roll and a cymbal for the
drum roll, claps for the applause, a whoosh for the rocket; short and soft. None plays where the person chose
`data-celebrations="none"`.

```twig {"preview":true}
<twig:Celebration message="Sound check" effect="applause" :sounds="true" soundLabel="Sounds">
    <button type="button" data-action="celebration#play">Applaud</button>
</twig:Celebration>
```

### A vibration on a phone

With `vibrate`, a device that can vibrate does so briefly when something is drawn, 600 milliseconds at most; nothing
happens on a device without the Vibration API, nor where motion is reduced or the person chose `none`.

```twig
<twig:Celebration message="{{ 'quiz.right'|trans }}" effect="fireworks" :vibrate="true" />
```

### Words in turn

Without a `message`, `messages` is said in turn, one at random each time, never the same twice in a row, so that a
screen reader does not repeat "Bravo" every time. `celebration_messages()`, of the bundle of phil/ux-kit, gives eight words of
praise in the language of the request, English or French; an application gives its own, translated by its translator, for other languages.

```twig {"preview":true}
<twig:Celebration :messages="celebration_messages()" effect="aurora">
    <button type="button" data-action="celebration#play">Say well done</button>
</twig:Celebration>
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
twice; it stays shown; a drum roll says it when its roll ends. Where motion is reduced, by the system or by
`data-motion="reduce"` on an ancestor, nothing is drawn and nothing is moved. There is no sound until the person turns
the sounds on by the button of `sounds`, a button with `aria-pressed`. The canvas is hidden from screen readers, lets every click through and never holds the page:
the animation lasts a few seconds at most, holds while the tab is hidden, and its canvas is removed at the end, or as
soon as the element leaves the page. One animation runs at a time.

Nothing flashes more than twice a second, under the three flashes of WCAG 2.3.1: fireworks burst twice a second at
most; the laser show draws thin lines at 60 % of their colour at most, never a fill of the screen, its waves half a
second apart, each beam fading in over 300 milliseconds and swinging at half a turn a second at most; the spotlight
dims the page over 400 milliseconds, to 45 % at most; the hands of the applause clap two and a half times a second at
most, and the sticks of the drum roll move without a change of light; the rainbow is drawn at half its colour.

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
