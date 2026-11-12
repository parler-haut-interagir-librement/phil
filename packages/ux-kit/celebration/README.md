# Celebration

A celebration for a victory: a message said in a polite live region, and confetti or fireworks drawn over the page on a
canvas, in the colours of the theme, for a few seconds at most. Where motion is reduced, only the message shows.

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

`message` is what is said when it plays, `effect` is `confetti`, by default, or `fireworks`, and `duration` the run of
the animation in milliseconds, kept between 600 and 4000. Nothing plays until something asks.

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

### By a button

A button inside it plays it by the action `celebration#play`; its params `message` and `effect` stand in for the props.

```twig
<twig:Celebration message="Well done">
    <button type="button" data-action="celebration#play" data-celebration-effect-param="fireworks">Celebrate</button>
</twig:Celebration>
```

## Accessibility

The message is said in a polite live region, emptied then written again each time, so that the same words are said
twice; it stays shown. Where motion is reduced, by the system or by `data-motion="reduce"` on an ancestor, nothing is
drawn. There is no sound. The canvas is hidden from screen readers, lets every click through and never holds the page:
the animation lasts a few seconds at most, holds while the tab is hidden, and its canvas is removed at the end, or as
soon as the element leaves the page. Fireworks burst twice a second at most. One animation runs at a time.
