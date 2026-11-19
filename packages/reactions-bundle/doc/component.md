# The component

```twig
<twig:PhilReactions content="article:42" />
```

`content` is a reference of `phil/resource`, as text or as a `ResourceReference`. Its props are signed: a browser
cannot change the content it reacts to, and the member is always the one of the request.

The same content can be drawn twice, at the start and the end of an article: the first to change asks the others to
draw themselves again.

## A page of many contents

Each component reads its content when it is drawn. A page of many contents reads them first, in one query for the
counters, one for the viewer's choices, and one for the names when they are shown:

```twig
{{ reactions_preload(contents) }}
```

`contents` is a list of references, as text or objects; in PHP, `ReactionBoard::preload()`. What it reads lasts for
the request.

## Without JavaScript

The component is a form posted to the route `phil_reactions_submit`, whose address is signed and names the content and
the page to come back to. Its token is Symfony's stateless CSRF token `phil_reactions`. A success comes back to the
page with `303`; anything else stops on `@PhilReactions/stopped.html.twig`, with `403`, `409`, `422` or `429`, which an
application overrides under `templates/bundles/PhilReactionsBundle/`. A reaction beyond the cap of `per_member`
answers `409`.

Each button says the state it wants, so a double click or a form sent twice changes nothing more.

## The accessibility

| What | How |
|---|---|
| Which reactions are the viewer's | `aria-pressed` on their buttons |
| The viewer reached the cap of `per_member` | A sentence after the bar, the other reactions not to be pressed |
| What a button is | The label of the reaction and its counter read aloud, the emoji and the digits hidden from screen readers; an image has its replacement text |
| What happened | A polite live region says the reaction was recorded, removed or refused |
| The focus | Kept on the same button across a render, by its `data-phil-focus` |
| A finger | The recipes make each button at least 2.75rem, or `min-h-11 min-w-11`, wide and tall |
| Who reacted | A disclosure, reachable by the keyboard, not a tooltip; the names a list written in the language of the page, "Ada, Linus and Grace", by `format_list` |

## The picker

The reactions that are not in the bar stand in a `<details>` of plain buttons. The controller
`phil--reactions-bundle--picker` adds:

| Key | What it does |
|---|---|
| Typing in the search | Keeps the reactions whose label holds the text, without case or accent, and says how many remain |
| Enter in the search | Chooses the first reaction shown |
| Down, from the search | Goes to the first reaction |
| Arrows | Move among the reactions shown; Up from the first goes back to the search |
| Home, End | The first and the last reaction shown |
| Escape, or a click elsewhere | Closes the picker; Escape puts the focus back on its summary |
