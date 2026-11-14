# JavaScript

## The controller

`mention` — `phil--mentions-bundle--mention` once registered — is an `@` autocomplete on a plain text field, a
`textarea` or an `input`:

```twig
<textarea name="body"
          data-controller="phil--mentions-bundle--mention"
          data-phil--mentions-bundle--mention-url-value="{{ path('phil_block_editor_mentions') }}"
          data-phil--mentions-bundle--mention-empty-value="{{ 'No member found'|trans }}"></textarea>
```

| Value | Default | Effect |
|---|---|---|
| `url` | none | The block editor's member search; without it, the list stays empty |
| `delay` | `150` | Milliseconds after the last key before the search |
| `empty` | `No member found` | What the list says, and the live region announces, when nobody matches |
| `found` | `{count} members found` | What the live region announces when members are listed, `{count}` replaced |

## What it does

- As the writer types `@` and up to 64 letters, digits, `_`, `.` or `-` where a word does not go on before it, it
  searches the members after the delay, the request before it cancelled.
- It lists the members that name a handle under the field: their name and `@handle`.
- The arrows move in the list, Enter or Tab writes the chosen handle and a space in place of what was typed, Escape or
  leaving the field closes it; a click chooses too.
- It dispatches `phil--mentions-bundle--mention:chosen` with `{handle, reference}`, and an `input` event on the field.

The field stays a plain text: the server finds the handles again when it is saved, and decides who is mentioned.

## Accessibility

The field is a `combobox` with `aria-autocomplete="list"`, `aria-controls` naming the list and `aria-expanded`; the
list is a `listbox` of `option`s, the active one `aria-selected` and named by `aria-activedescendant`, so screen
readers follow the choice while the focus stays in the field. A visually hidden `role="status"` after the list
announces how many members were found, or that nobody was, and is emptied when the list closes. The attributes and
the elements are removed when the controller disconnects. The browser suite checks each of them in Chrome.

## The request

A `GET` of `url?q=<query>` with the header `X-Phil-Block-Editor: 1`, on the same origin, with the session's
credentials: the block editor's endpoint changes nothing and asks no CSRF token. A refused or failed request lists
nobody.

## Modules

| Module | Role |
|---|---|
| `mention_controller.js` | The controller |
| `lib/query.js` | `activeQuery(text, caret)`, `insertMention(text, range, handle)` and `handleOf(item)`, pure |
| `lib/api.js` | `suggestions(url, query, fetcher, signal)`, the search and its suggestions that name a handle |

## The stylesheet

`phil-mentions/mentions.css` places the list under the field — its parent sets `position: relative` — and styles it
and `.mention` from PHIL UX's tokens or the page's colours, with an outline in forced colours.
