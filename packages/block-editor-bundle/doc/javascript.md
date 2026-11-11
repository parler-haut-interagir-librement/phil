# JavaScript

## The controller

`assets/src/editor_controller.js` is a Stimulus controller, registered as `phil--block-editor-bundle--editor`. The
form theme writes its markup; a page may write it by hand, to show a document read-only outside a form for instance:

```html
<div class="phil-block-editor" data-controller="phil--block-editor-bundle--editor"
     data-phil--block-editor-bundle--editor-config-value='{"readOnly": true, "locale": "fr"}'>
    <input type="hidden" value="{{ article.body }}" data-phil--block-editor-bundle--editor-target="input">
    <div data-phil--block-editor-bundle--editor-target="canvas"></div>
    <div class="phil-block-editor__status" data-phil--block-editor-bundle--editor-target="status" role="status"></div>
</div>
```

| Target | Role |
|---|---|
| `input` | The field holding the UJO JSON, read when the editor connects and written after each change |
| `canvas` | Where the blocks are drawn; created when missing |
| `status` | A live region announcing moves, deletions and uploads |

The `config` value is one object:

| Key | Default | Meaning |
|---|---|---|
| `tools` | every block tool | The block types offered |
| `inlineTools` | every inline tool | The inline tools offered |
| `readOnly` | `false` | Show without editing |
| `placeholder` | the label `placeholder` | The hint of an empty paragraph |
| `locale` | the page's `lang` | The language of the labels |
| `messages` | `{}` | Labels replaced, by key |
| `colors` | `[]` | The colour tokens of the text colour tool |
| `uploadUrl`, `mentionsUrl`, `linksUrl` | `null` | The endpoints; `null` turns the feature off |
| `csrfCookie` | `null` | The name of the cookie and header of the double-submitted CSRF token; `null` sends none, and the upload is refused |

The editor never refuses to open: JSON it cannot read gives an empty document, and the server judges what it
receives.

## Events

| Event | On | Detail |
|---|---|---|
| `change` | the input | — fired each time the editor writes a new value, for autosave or a dirty-form warning |
| `phil--block-editor-bundle--editor:change` | the editor's element | `{json}`, the value written |
| `phil--block-editor-bundle--editor:focus` | the editor's element | `{id}`, the block the writer entered; `null` when the focus leaves the editor, its menus and its toolbar |

## Writing together

The editor listens on its element for the events a collaboration controller — that of `phil/edit-lock-bundle` —
dispatches. None of them enters the undo history: a block another writer replaced cannot be undone locally, so the
history starts over after each one.

| Event | Detail | What the editor does |
|---|---|---|
| `phil-block-editor:apply` | `{block, list, after}` | Puts the block in place of the block of the same identifier, wherever it is nested; otherwise inserts it in the list `list` (`root`, a block's id, or a block's id and a column, `id:0`) after the block `after`, first when `after` is `null`, at the end of the document when the list is nowhere. Only that block is drawn again, so the caret stays where the writer is |
| `phil-block-editor:remove` | `{id}` | Removes the block |
| `phil-block-editor:lock` | `{blocks: {id: name}}` | Closes the blocks named — `inert`, outlined, the name shown above them — and opens the others |

The document is written to the input after `apply` and `remove`, outside read-only mode, as after any change.

## Labels

The labels are in English and French in `assets/src/lib/i18n.js`; a locale such as `fr_CA` takes French, an unknown
one English. Any label can be replaced through the field's `messages` option or the `messages` key, by its key —
`placeholder`, `delete`, `turnInto`, `color.accent`, and the others of the file. `{type}` in `foreign` and
`converted` is replaced by the type.

## Modules

The controller is built on modules without the DOM, each tested under Node, and two that need a browser:

| Module | Role |
|---|---|
| `lib/inline.js` | The inline sanitizer: the tools of `phil/ujo`'s `rich` profile, its canonical form, its address and attribute rules, styles read as marks |
| `lib/paste.js` | Pasted HTML or text turned into blocks of the toolset |
| `lib/model.js` | UJO read and written, blocks found, moved and placed in nested lists, list items, table rows and columns, the undo history |
| `lib/tools.js` | Each block type's data, its text and conversions, the slash menu, the block menu's actions |
| `lib/menu.js` | Menu matching and selection, the `/`, `@` and `[[` triggers, the HTML of a chosen suggestion |
| `lib/keys.js` | The shortcuts and the Markdown-like marks |
| `lib/i18n.js` | The labels |
| `lib/csrf.js` | A new random token for each request, its cookie, its header, and the field of a form |
| `lib/caret.js` | The caret and the selection in a field, splitting, inserting, marks; needs a browser |
| `lib/view.js` | The blocks drawn as elements; needs a browser |

Every HTML the editor draws goes through `lib/inline.js` first, and links and image addresses are checked by the same
rules as on the server.

## The stylesheet

`assets/styles/block-editor.css` styles the editor under `.phil-block-editor` with custom properties: `--pbe-text`,
`--pbe-muted`, `--pbe-surface`, `--pbe-sunken`, `--pbe-border`, `--pbe-accent`, `--pbe-accent-soft`, `--pbe-focus`,
`--pbe-danger`, `--pbe-info`, `--pbe-success`, `--pbe-warning`, `--pbe-mark`, `--pbe-radius`, `--pbe-mono`,
`--pbe-gutter` and `--pbe-shadow`. A block another writer holds, `.pbe-locked`, is outlined in `--pbe-info` with the
name from its `data-locked-by` attribute. Each takes PHIL UX's token of the same role when that stylesheet is loaded,
and otherwise a value drawn from the page's `Canvas` and `CanvasText`, so it follows the page's light or dark scheme.
Redefine them on `.phil-block-editor` to change the look; the five default colour tokens are styled there too, and a
site's own tokens need a rule each.
