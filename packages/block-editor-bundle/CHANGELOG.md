# Changelog

All notable changes to `phil/block-editor-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-20

Marks at a collapsed caret, a mark removed from a selection that ends on its edges,
and rich fields exposed as named multi-line text boxes.

### Added

- Each editable rich field is a multi-line text box for assistive technologies: `role="textbox"`,
  `aria-multiline="true"` and an accessible name, the name of its block (*Text*, *Heading 2*, *Quote*, *Caption*…),
  *List item* for a list item, and *Row 1, column 2* for a table cell; the `listItem` and `cell` labels, in English
  and French.
- `doc/prosemirror-trial.md`, an archived trial: a ProseMirror view in each rich field, measured against the editor's
  own fields over 26 scenarios, with the weight and drawing times; the editor keeps its own fields.
- `tests/Browser/measure.mjs`: the trial's scenarios, run on the editor's own fields in a headless Chrome over the
  DevTools protocol, with two more scenarios on a mark turned on or off at the caret.

### Fixed

- A mark's shortcut at a collapsed caret now applies to what is typed next: an empty mark waits at the caret, and the
  same shortcut inside a mark splits it there, so that the text typed next goes without it. Before, the shortcut did
  nothing without a selection, and inside a mark it removed the whole mark and moved the caret to the start of the
  field.
- A mark's shortcut on a selection whose ends sit just outside the mark's element removes the mark. Before, it added a
  second mark and left an empty one behind (`one <b>two</b><b></b> three`).

## 0.1.0-alpha - 2026-11-11

### Added

- `Toolset`: the block types and inline tools a field offers, named as `phil/ujo` names them, with the `full` and
  `reduced` presets; `InvalidToolset` for an unknown tool or preset, or a toolset without paragraphs.
- `ToolsetNormalizers`: one `phil/ujo` normalizer per toolset, on the application's block type registry, refusing the
  block types the toolset leaves out and unwrapping its inline elements.
- `DocumentReferences`: the mentions, page links and file addresses of a document, for usage tracking.
- `PhilBlockEditorBundle`, configured under `phil_block_editor`: `preset`, `tools`, `inline_tools`, `colors`,
  `image_uploader`, `mention_search`, `link_search`, `max_upload_bytes` and `access`; the editor's CSRF token added to the
  stateless ones of FrameworkBundle, and `CsrfProtectionPass`, which stops the container with a message when
  `framework.csrf_protection` is false; the controller and the stylesheet mapped for AssetMapper.
- `BlockEditorType` and `BlockEditorTransformer`: the editor as a form field, its JSON checked by the application's
  `Ujo` and narrowed to the toolset, kept as canonical JSON or a `Document`, a document without blocks being no
  value; options for the preset, the tools, read-only mode, the placeholder, the locale, the labels, the colours,
  the endpoints and foreign blocks; the name of the CSRF cookie given to the editor.
- The form theme `@PhilBlockEditor/form/block_editor.html.twig`, added to Twig's: the hidden input, the editor's
  element and its live region, and a label the editor refers to.
- `ImageUploader`, `ImageUpload`, `UploadedImage` and `UploadRefused`: the port behind image uploads, and
  `ImageUploadController`, answering as the Editor.js image tool expects.
- `ReferenceSearch` and `Suggestion`: the port behind the `@` and `[[` menus, `InvalidSuggestion`, and
  `ReferenceSearchController`.
- `EditorRequest` and `Endpoints`: the `access` attribute, `IS_AUTHENTICATED` by default, granted by the
  application's authorization checker before the uploader or a search is called, every call refused without one,
  none checked when it is null; the editor's own header, the browser's `Sec-Fetch-Site` and Symfony's stateless CSRF
  check of the token `phil_block_editor` on the upload, the `GET` searches carrying none, and the endpoints' URLs given
  to the editor when their service is configured and their routes imported; the routes in `config/routes.php`.
- The `editor` Stimulus controller: blocks edited in place and written back as UJO, the slash menu, the + button,
  drag handles, the block menu (turn into, type actions, duplicate, move, delete), block selection, the inline
  toolbar (bold, italic, underline, strikethrough, inline code, link, highlight, colour, superscript, subscript),
  keyboard shortcuts and Markdown-like marks, undo and redo, nested lists, tables, toggles and columns, `@` mentions
  and `[[` page links, image uploads by button, drop or paste, clean paste from Word, Google Docs and HTML, blocks of
  other types kept as they are, read-only mode, labels in English and French, a new CSRF token sent with each
  upload. Writing together: the `focus` event
  for the block the writer is in, and the `phil-block-editor:apply`, `remove` and `lock` events, by which a
  collaboration controller puts in place the blocks other writers save, removes those they remove, and closes those
  they hold.
- The pure modules of the controller — inline sanitizer, paste reader, document model with the placing of a block saved
  elsewhere, tools, menus, keyboard, labels, the double-submitted CSRF token — and their tests under Node's test runner.
- The stylesheet `block-editor.css`, on custom properties that follow PHIL UX or the page's own colours.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
