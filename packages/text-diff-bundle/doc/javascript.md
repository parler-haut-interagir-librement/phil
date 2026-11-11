# JavaScript

## The controller

`assets/src/comparison_controller.js` is a Stimulus controller, registered as `phil--text-diff-bundle--comparison`. It
is placed around a difference written by either filter:

```twig
<section data-controller="phil--text-diff-bundle--comparison"
         data-phil--text-diff-bundle--comparison-counter-text-value="Change {current} of {total}"
         data-phil--text-diff-bundle--comparison-total-text-value="{total} changes"
         data-phil--text-diff-bundle--comparison-none-text-value="No changes">
    <div role="group" aria-label="View">
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="inline" data-phil--text-diff-bundle--comparison-target="mode">Both</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="old" data-phil--text-diff-bundle--comparison-target="mode">Before</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="new" data-phil--text-diff-bundle--comparison-target="mode">After</button>
    </div>
    <button type="button" data-action="phil--text-diff-bundle--comparison#previous">Previous change</button>
    <button type="button" data-action="phil--text-diff-bundle--comparison#next">Next change</button>
    <p role="status" data-phil--text-diff-bundle--comparison-target="counter"></p>
    <div data-phil--text-diff-bundle--comparison-target="output">{{ old|text_diff(new) }}</div>
</section>
```

| Target | Role |
|---|---|
| `output` | Where the marks are looked for; the controller's element when absent |
| `counter` | A live region saying how many changes the view shows, or which one is current |
| `mode` | A button with a `mode` param; `aria-pressed` says whether its view is shown |

| Value | Default | Meaning |
|---|---|---|
| `mode` | `inline` | The view: `inline`, both texts marked; `old`, the old text alone; `new`, the new text alone |
| `counter-text` | `{current} of {total}` | The counter while a change is current |
| `total-text` | `{total} change(s)` | The counter when none is |
| `none-text` | `No changes` | The counter when the view shows no change |

| Action | What it does |
|---|---|
| `show` | Shows the view of its `mode` param; an unknown one shows the inline view |
| `next`, `previous` | Moves to the following or preceding change the view shows, round the ends |

## What it does

The view is written on the element as `data-text-diff-mode`, which the stylesheet reads to hide the insertions of the
old view and the deletions of the new one. Changing the view clears the current change.

A change is a mark the view shows: every `<ins>` and `<del>` in the inline view, a deletion followed by its insertion
— nothing but white space between them — counting as one replacement; the deletions alone in the old view; the
insertions alone in the new view. The current change gets the class `text-diff-current`, its first mark
`aria-current="true"` and the focus, and is scrolled to the middle of the screen — at once when the reader prefers
reduced motion, smoothly otherwise.

Labels come from the values, so a page writes them in its own language. Keyboard shortcuts are the page's to add, as
Stimulus actions on the element:

```html
data-action="keydown.n->phil--text-diff-bundle--comparison#next keydown.p->phil--text-diff-bundle--comparison#previous"
```

## Its tests

`tests/js` runs under Node's test runner, Stimulus replaced by a stub:

```sh
node --import ./tests/js/register.mjs --test tests/js
```
