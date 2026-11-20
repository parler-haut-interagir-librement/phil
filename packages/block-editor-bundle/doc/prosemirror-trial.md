# ProseMirror trial

**Archived trial.** The editor keeps its own rich fields. The trial's `richEngine` option, its modules and the
`prosemirror-*` dependency are not part of the bundle since 0.1.1-alpha. That version also fixes the two failures of
`lib/caret.js` the trial found, and gives each editable field a `textbox` role and an accessible name. The trial
would be reopened if Android keyboards or a screen reader such as VoiceOver showed a gap the editor cannot close.

The trial put a [ProseMirror](https://prosemirror.net/) view inside each rich field, in place of the editor's own
`contenteditable` handling. It was turned on per field and left everything else to the controller: the Stimulus
envelope, the blocks and their coordination, the slash, `@` and `[[` menus, the toolbar, the paste of blocks, the undo
history and the UJO (Universal JSON Output) written to the input. This page says how it was wired, how it was measured
against the editor's own fields, and what the numbers were.

## How it was turned on

```html
<div data-controller="phil--block-editor-bundle--editor"
     data-phil--block-editor-bundle--editor-config-value='{"richEngine": "prosemirror"}'> … </div>
```

| Key | Values | Meaning |
|---|---|---|
| `richEngine` | `"dom"` (default), `"prosemirror"` | Who edits the text and marks of a rich field; read-only mode always draws plain HTML |

The page's import map needed `prosemirror-model`, `prosemirror-state`, `prosemirror-transform` and `prosemirror-view`
(and `orderedmap`, which `prosemirror-model` imports). `assets/package.json` listed the four, so that
`importmap:require` added them, with the module they depend on, when the bundle's controller was installed. The
trial's module, `lib/prosemirror.js`, was imported dynamically: a field without `richEngine: "prosemirror"` never
loaded ProseMirror. Webpack Encore was not involved.

## How it was wired

| Module | Role |
|---|---|
| `lib/rich-schema.js` | The schema as plain specs, without the DOM and without ProseMirror: one mark per inline tool of the `rich` profile, parsed from every element the tool accepts by the sanitizer's own `match` and written in its canonical form; a line break as the only node besides text; links of every kind excluding each other and not growing at their end; the inline styles Word and Google Docs write read as marks, Google Docs' bold wrapper of normal weight refused. Tested under Node |
| `lib/prosemirror.js` | The view mounted on the field itself (`{mount: field}`), so the field keeps its element, class and data attributes; the document read through an inert `<template>`, written through `DOMSerializer` and then the sanitizer; marks toggled, HTML and line breaks inserted, a field split at the caret, all as transactions |
| `editor_controller.js` | `loadEngine()` imports the module after the first drawing, `mountRich()` gives each rich field a view after every drawing and destroys the views of fields drawn away; `deleteBefore`, `insertHtml`, `insertLineBreak`, `splitAtCaret` and `toggleMark` go to the view when the field has one, to `lib/caret.js` otherwise; `html(field)` reads the view's document; a view reports its changes through `input()` once it has read the DOM, and the controller's own `input` listener leaves its fields alone |
| `lib/caret.js` | The line break ProseMirror draws at the end of an empty line counts as nothing for `atStart` and `atEnd` |
| `block-editor.css` | The view's emptiness as `.pbe-rich-empty`, which shows the placeholder over the line |

ProseMirror's history, keymap and commands are not used. Enter, Backspace, Delete, the arrows, Tab and the shortcuts
reach the controller as before; a paste is left to the controller (`handleDOMEvents.paste`), which turns it into
blocks and inserts inline HTML through the view.

## Method

`tests/Browser/measure.mjs` runs the same 26 scenarios on two pages that differ only by `richEngine`, in a headless
Chrome 158 driven over the DevTools protocol, each scenario on a fresh page:

- keys are sent with `Input.dispatchKeyEvent`, so the page receives `keydown`, `beforeinput` and `input` as from a
  keyboard; the command key is ⌘ on a Mac;
- IME input is sent with `Input.imeSetComposition` and `Input.insertText`, which make Chrome raise its own
  `compositionstart`, `compositionupdate`, `compositionend` and composing `input` events, as an input method does;
- a paste is a `ClipboardEvent` carrying a `DataTransfer` with `text/html` and `text/plain`, from the fixtures of
  `tests/Browser/paste-fixtures.mjs` (Word for Windows, Google Docs, a web page with traps), scored by the marks kept,
  the text present and nothing forbidden arriving;
- the result is the list of block texts the controller saves into its input.

The same run reads the accessibility node Chrome gives a screen reader for the first field, counts the bytes and
requests the page loads with the cache off, and times the drawing of 50 and 500 paragraphs. The weight served by
AssetMapper is measured on the files themselves: the bundle's as AssetMapper serves them, without a minifier, and
ProseMirror's as `importmap:require` downloads them, the minified `+esm` builds of jsDelivr. Sizes are summed per file,
since each is its own request, compressed with `gzip -9` and `brotli -q 11`.

```bash
npm ci --ignore-scripts --prefix packages/block-editor-bundle/tests/Browser
node packages/block-editor-bundle/tests/Browser/measure.mjs
```

The script now runs the editor's own fields alone, with two more scenarios on a mark turned on or off at the caret;
the second engine comes back with the trial's modules (see [Reopening the trial](#reopening-the-trial)).

Versions measured: `prosemirror-model` 1.25.12, `prosemirror-state` 1.4.4, `prosemirror-transform` 1.12.4,
`prosemirror-view` 1.42.9, `orderedmap` 2.1.1, all MIT.

## Results

### Weight

| What is served | Raw | gzip | Brotli |
|---|---:|---:|---:|
| The editor today: controller, 10 modules, stylesheet | 173,057 B | 45,655 B | 39,020 B |
| The same files with the trial's switch, `richEngine: "dom"` | 177,017 B | 46,587 B | 39,838 B |
| The trial's own modules, `prosemirror.js` and `rich-schema.js` | 11,869 B | 4,321 B | 3,634 B |
| ProseMirror through `importmap:require` (model 45,263, view 99,174, transform 32,969, state 12,460, orderedmap 1,799) | 191,665 B | 62,149 B | 55,713 B |
| **The editor with `richEngine: "prosemirror"`** | **380,551 B** | **113,057 B** | **99,185 B** |

ProseMirror multiplies what the editor sends by 2.2 raw and 2.5 compressed: about 60 KB more over Brotli. On the
measuring page, which serves npm's unminified builds without compression, the editor loads 15 requests and 272,848 B
with its own fields, 22 requests and 788,530 B with ProseMirror.

### Behaviour

| Scenario | Own fields | ProseMirror |
|---|:---:|:---:|
| Typing | pass | pass |
| Each of 8 marks by its shortcut on a selection (bold, italic, underline, strikethrough, inline code, marker, superscript, subscript) | 8 / 8 | 8 / 8 |
| A mark at a collapsed caret, then typing | **fail**: `plain bold`, the shortcut does nothing without a selection | pass |
| A mark removed by its shortcut again, the selection ending on the text's edges | **fail**: `one <b>two</b><b></b> three`, a second empty mark | pass |
| Enter splits at the caret; Enter then typing; Backspace at the start merges; Shift+Enter writes a line break | 4 / 4 | 4 / 4 |
| Undo of the last burst of typing; redo; undo of a split block | 3 / 3 | 3 / 3 |
| IME: Japanese composed then committed; composition inside bold text; a full-width `＃` composition left a paragraph | 3 / 3 | 3 / 3 |
| Dead key: `´` then `e` gives `é` | pass | pass |
| Emoji and characters outside the BMP | pass | pass |
| Paste from Word, Google Docs and a web page, through the controller | 3 / 3 | 3 / 3 |
| **Total** | **24 / 26** | **26 / 26** |

The two failures are in `lib/caret.js` and do not need ProseMirror to be fixed: a stored mark at a collapsed caret,
and finding the mark around a selection whose ends sit just outside its element. Both are fixed in 0.1.1-alpha: the
editor's own fields pass the 26 scenarios, and the two added on a mark at the caret.

### Paste

In both engines a paste goes through the controller's `lib/paste.js` and the sanitizer, so the blocks are the same.
The fixtures were also read by ProseMirror's own parser with the trial's schema, as the view would read them if it
handled the paste itself: 3 / 3 fixtures pass in both, with every mark kept (Word 7 / 7, Google Docs 7 / 7, web page
5 / 5), the text complete, and no address, handler, script, image or style leaking. One difference: ProseMirror nests
marks in the order of the schema, so Google Docs' underlined link is written `<u><a>…</a></u>` where the sanitizer
keeps `<a><u>…</u></a>`; the server accepts both.

### Accessibility

The node Chrome exposes for a field is the same in both engines: role `generic`, `editable: richtext`, focusable,
no accessible name. ProseMirror adds `translate="no"` to the field, which keeps page translators from rewriting the
text being edited. Neither engine gave a field a `textbox` role or a name: that gap was the editor's, not the
engine's. Since 0.1.1-alpha, Chrome exposes each editable field as role `textbox`, `multiline`, named by its block
(*Text* for a paragraph).

### Time

| Document | Own fields | ProseMirror |
|---|---:|---:|
| 50 paragraphs, from the controller's start to editable fields (median of 5, modules fetched, cache off) | 6 ms | 37 ms |
| 500 paragraphs, same | 15 ms | 88 ms |
| 50 paragraphs, drawn again (median of 9) | 0.9 ms | 3.9 ms |
| 500 paragraphs, drawn again (median of 9) | 7.1 ms | 34.3 ms |

The controller draws the whole document again after each change of structure (a block added, split, merged, moved,
an undo). With ProseMirror every drawing destroys and creates every view: 34 ms at 500 paragraphs is two frames. A
drawing that replaces only the blocks that changed would remove that cost for both engines.

## Not measured

| What | Why |
|---|---|
| Android keyboards (Gboard, Samsung), which send composition and `input` events without `keydown` | No Android device or emulator on the measuring machine; ProseMirror documents and handles these cases, the editor's own fields were never tested on them |
| A real input method's candidate window, and dead keys typed on a physical keyboard | Chrome's composition events were produced through the DevTools protocol, not by macOS's input methods |
| VoiceOver, NVDA, JAWS | No screen reader can be driven headless; only the accessibility tree was compared |
| Safari and Firefox | The measures ran in Chrome only |
| Clipboards written by the applications themselves | The fixtures reproduce the shapes Word, Google Docs and web pages write; a paste from the system clipboard was not made |
| The bundle's Panther tests | The bundle has none: its controller has been checked in a browser by hand. The other bundles' Panther suites could not start on the measuring machine, whose chromedriver (156) does not drive its Chrome (158) |
| Memory | Not read; 500 views were created without error |

## Reopening the trial

The trial's modules, `lib/prosemirror.js` and `lib/rich-schema.js` with its Node tests, and the controller's
`richEngine` switch are in the package's history, in the commit that adds this page. Reopening the trial means
restoring them, listing the four `prosemirror-*` modules in `assets/package.json` and in
`tests/Browser/package.json`, and adding `prosemirror` back to the engines of `measure.mjs` and of its page,
`router.php`. The document format does not change: both engines write the same UJO.
