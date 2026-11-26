# Podium

The first three of a ranking on their steps: they rise one after the other, the winner last, when the podium comes into
view, then the winner is said and the `Celebration` of the page may play. A tournament, a contest, a leaderboard.

```twig {"preview":true}
<twig:Podium :places="[{name: 'Ada', score: 42}, {name: 'Grace', score: 37}, {name: 'Alan', score: 31}]" message="Ada wins with 42 points" :autoplay="false">
    <button type="button" data-action="podium#rise">Reveal the podium</button>
</twig:Podium>
```

## Installation

::: installation

## Usage

```twig
<twig:Podium :places="leaders" message="{{ 'contest.winner'|trans({name: leaders[0].name}) }}" />
```

`places` lists the first three in their order, each a `name` and an optional `score`; any more are left out, and fewer
stand on fewer steps. `label` names the list for screen readers, `Podium` by default. `message` is said once they stand
on their steps. With `autoplay`, by default, they rise as soon as the podium comes into view; without it, by the action
`podium#rise`, a button in its block `content`.

## Examples

### With a celebration

`effect` plays the `Celebration` of the page once they stand: the podium says its message, the celebration draws. Give
the celebration no message of its own, so that the words are said once.

```twig {"preview":true}
<twig:Podium :places="[{name: 'Team Red'}, {name: 'Team Blue'}, {name: 'Team Gold'}]" message="Team Red wins" effect="confetti" :autoplay="false">
    <button type="button" data-action="podium#rise">Reveal the winners</button>
</twig:Podium>
<twig:Celebration />
```

### Two places

```twig {"preview":true}
<twig:Podium :places="[{name: 'Ada', score: '3–1'}, {name: 'Alan', score: '1–3'}]" label="Final" message="Ada wins the final" :autoplay="false">
    <button type="button" data-action="podium#rise">Reveal</button>
</twig:Podium>
```

## Accessibility

The places are an ordered list in the order of the ranking, named by `label`: screen readers read the first first,
whatever the screen shows, the second, the first and the third side by side. The number on each step is hidden from
them, the list already giving it. The message is said in a polite live region. Where motion is reduced, by the system
or by `data-motion="reduce"` on an ancestor, the places stand on their steps from the start and nothing moves; the rise
lasts 1.2 seconds at most and never flashes. There is no sound.

## Security

Nothing is written in a `style` attribute and nothing runs inline: the states of the rise are a `data-state` attribute
the stylesheet reads. The names and the scores are escaped.
