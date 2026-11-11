# The editor

The editor shows a document as a column of blocks. Each block has, on hover or focus, a gutter with two buttons: **+**
adds a paragraph below and opens the slash menu in it, **⋮⋮** is the handle that moves the block and opens its menu.

## Adding and changing blocks

| Way | What it does |
|---|---|
| `/` at the start of a block or after a space | Opens the slash menu; letters typed after it filter the entries, in the labels and in words such as `h1`, `todo`, `callout`, `divider` in English and French. ↑ ↓ choose, Enter or Tab insert, Escape closes. In an empty paragraph the chosen block takes its place; otherwise it comes after |
| Marks typed at the start of a paragraph | `#` to `######` and a space for a heading, `-`, `*` or `+` for a bulleted list, `1.` for a numbered one, `[]` or `[x]` for a to-do list, `>` for a quote, `>>` for a toggle, `!` for a callout, ` ``` ` then a language and a space for code, `---` for a divider |
| The block menu, from the handle | **Turn into** another type holding text; the actions of the type (heading level, list style, callout type, quote alignment, image border, background and width, table header row, rows and columns, column count); **Duplicate**, **Move up**, **Move down**, **Delete** |
| Shortcuts | See below |

Turning a list into paragraphs gives one paragraph per item; a toggle turned into another type leaves its blocks
after it; text turned into a list gives one item per line.

## Moving blocks

Drag a block by its handle, before or after another block, into a toggle, into a column, or onto the button of an
empty toggle or column. A block never moves into itself. From the keyboard, ⌘⇧↑ and ⌘⇧↓ (Ctrl+Shift on other
systems) move the block within its list.

**Escape** selects the block the caret is in. A selected block is outlined; ↑ ↓ select the block before or after,
⌘⇧↑ ↓ move it, ⌘D duplicates it, Backspace or Delete removes it, Enter goes back to its text, Escape lets go.

## Writing

| Key | In a text | In a list item | In a table cell |
|---|---|---|---|
| Enter | Splits the block: what follows the caret becomes a new paragraph; in an open toggle, its first block | Splits the item; in an empty item, leaves the list, or a nested level | Goes to the cell below, adding a row at the end |
| Shift+Enter | A line break, when the toolset has `lineBreak` | the same | the same |
| Backspace at the start | Joins the paragraph to the text before it, removes an empty one, turns a heading, quote, callout or toggle into a paragraph | Joins the item to the one before, takes a nested item out, turns a single item into a paragraph | — |
| Delete at the end | Pulls the next paragraph in | — | — |
| Tab, Shift+Tab | — | Nests the item under the one before, or takes it out | — |
| ↑ ↓ on the first or last line, ← → at the ends | Goes to the text before or after | the same | the same |

In a code block, Enter is a new line, ⌘Enter adds a paragraph after it, and Backspace in an empty one removes it.

## Formatting

Selecting text shows the toolbar of the field's inline tools: bold, italic, underline, strikethrough, inline code,
link, highlight, colour, superscript and subscript. A link asks for its address, which must be `http`, `https`,
`mailto`, `tel` or relative; the same button changes or removes it. The colours are the tokens of the configuration,
and **Default colour** removes one.

| Shortcut | Action | Shortcut | Action |
|---|---|---|---|
| ⌘B | Bold | ⌘. | Superscript |
| ⌘I | Italic | ⌘, | Subscript |
| ⌘U | Underline | ⌘Z, ⌘⇧Z or ⌘Y | Undo, redo |
| ⌘⇧X | Strikethrough | ⌘D | Duplicate the block |
| ⌘E | Inline code | ⌘⌥0 | Turn into a paragraph |
| ⌘K | Link | ⌘⌥1, 2, 3 | Turn into a heading |
| ⌘⇧H | Highlight | ⌘⌥4, 5, 6, 7, 8 | To-do list, bulleted, numbered, toggle, code |

On systems other than Apple's, Ctrl stands for ⌘ and Alt for ⌥. Undo and redo are the editor's own: they cover
typing, by pauses, and every change of structure.

## Mentions and page links

With a mention search configured, `@` and the letters after it open a menu of suggestions from the application; the
chosen one becomes `<a data-mention="user:42" href="…">@Ada</a>`. With a link search, `[[` does the same for pages:
`<a data-ref="page:7" href="…">Page title</a>`. See [integrations.md](integrations.md).

## Pasting

| Pasted | Becomes |
|---|---|
| Text on one line, or HTML of inline text | Inserted at the caret, cleaned |
| Text on several lines | One paragraph per blank-line-separated part, single new lines kept as line breaks |
| HTML of a page, of Word or of Google Docs | Blocks: paragraphs, headings, bulleted, numbered and to-do lists with their nesting — Word's list paragraphs included, its bullets dropped —, quotes, code, tables with a header row, dividers and images with a `http`, `https` or relative address |
| An address, over selected text | A link on that text |
| An image file | An image block, uploaded, when uploads are configured |

Inline text keeps the marks of the toolset only. Styles become marks — bold weights, italics, underlines and
strikethroughs, raised and lowered text — and Google Docs' wrapper of normal weight is not taken for bold. Scripts,
styles, comments and Word's conditional parts are dropped. A type the toolset lacks falls back to paragraphs. In a
list item, a table cell or a caption, everything pasted is inserted as inline text.

## Nested blocks

A toggle holds blocks under its title, shown or hidden by its arrow; whether it is open is saved with it. Columns
hold a list of blocks each, side by side, one under the other on narrow screens. Both take any block, nested ones
included, up to the depth `phil/ujo-bundle` allows.

## Images

An empty image block offers **Choose an image** — or a file dropped on it — when uploads are configured, and an
address field in any case. Once it has an image, its caption is edited in place, and the block menu sets its border,
background and width, or replaces it.

## Read-only mode

With `read_only` or a disabled field, the editor shows the document without gutters, menus or editable text. Toggles
still open and close, without saving anything. An empty document reads *Nothing has been written yet.*

## Accessibility

The editor is a group named by the field's label. Menus are listboxes or menus driven by the keyboard, their active
entry announced; the toolbar is a toolbar of named, pressed buttons; the gutter buttons have names. Moves, deletions,
duplications and conversions are announced in a polite live region. Animations stop under reduced motion.

## Saving

The editor writes the document into the hidden input a moment after each change, and at once when the form is
submitted. Empty blocks are left out: an empty paragraph, an image without address, a table of empty cells. A document
with no block left writes the empty text. Each write fires `change` on the input and
`phil--block-editor-bundle--editor:change` on the editor ([javascript.md](javascript.md)).
