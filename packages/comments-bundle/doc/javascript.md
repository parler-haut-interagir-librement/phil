# JavaScript

## The controller

```twig
<div data-controller="phil--comments-bundle--comments"
     data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--comments-bundle--comments-target="panel"></aside>
</div>
```

`comments` is `ClientConfig::for()`, to which the page may add:

| Key | Default | Role |
|---|---|---|
| `locale` | the page's `lang` | `en` or `fr` labels; another language takes English |
| `messages` | none | Labels of the application's own, by key |
| `refresh` | `0` | Seconds between two loads of the threads; `0` loads them once |

The controller finds the block editor inside its element; it works the same around a read-only editor, where
threads can be read, opened and answered but no suggestion accepted. Without an editor — a published article, a page
of a site —, only the whole page is commented on:

```twig
<section data-controller="phil--comments-bundle--comments"
         data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}"></section>
```

`ClientConfig::for($subject, $viewer, page: false)` leaves out the form of the page, for a review screen where only
passages are discussed; threads already on the page are still listed. A page that should work without any script
draws the comments on the page on the server instead: see [the page](page.md).

The controller is declared `"fetch": "lazy"`: StimulusBundle loads it only when an element of the page names it.

## Mentions

With `phil/mentions-bundle` enabled and the block editor's routes imported, `ClientConfig::for()` gives `mentions`, the
address of the member search. Every text area of the panel — the composer, a reply, an edit — then takes the `mention`
controller of that bundle — the form of the page too —, whose `@` autocomplete writes the chosen handle and says how
many members it found; its controller and stylesheet are registered by that bundle. Without the address, the text areas
stay plain.

The field remains plain text: the server finds the handles again when that bundle records the comment, as each comment
is written, and only the members who may see the comment are notified, as `phil/mentions-bundle` decides.

## What it does

- **Selection.** When the reader selects words inside one field of a block, a small toolbar offers *Comment* and
  *Suggest*. A selection across two fields, or blank, offers nothing.
- **Composer.** The passage, a text area and, for a suggestion, the replacement — the passage itself to start with,
  emptied to propose a removal. Sending opens the thread.
- **Panel.** The threads in the order of their passages in the document, the orphaned ones — whose passage is found
  neither by the server nor in the editor — last, under *Orphaned: the quoted passage is no longer in the text*;
  resolved ones on request. Each shows its passage, its state, its suggestion as a deletion and an insertion, its
  comments with author and date, a reply form, and the buttons the server's `may` allows.
- **The page.** Under the threads on passages, a section headed *On the page* lists the threads on the whole page and
  ends with a form to write one: sent, the thread joins the list and the form is empty again; refused, the text stays
  in the form and the panel's status says why.
- **Highlights.** Open threads' passages are highlighted in the editor through the CSS Custom Highlight API, which
  colours text without touching the editor's markup; the active thread's in a stronger colour. As the writer types,
  each passage is found again in the editor with the rules of [anchoring](anchoring.md).
- **Navigation.** A click on a highlighted passage brings its thread forward in the panel; a click on a thread's
  passage in the panel scrolls the editor to it.
- **Accepting.** The passage is replaced in the editor's block, the acceptance recorded, and the block applied to the
  editor by its `phil-block-editor:apply` event; see [suggestions](suggestions.md).

## Modules

| Module | Role |
|---|---|
| `comments_controller.js` | The controller: selection, panel, highlights, actions |
| `lib/passage.js` | Characters counted as the server counts them; the text of inline HTML; a passage found again and replaced; fields of the editor and their paths; DOM points and character offsets |
| `lib/api.js` | The requests to the endpoints, with the controller's header and, for a `POST`, a new CSRF token |
| `lib/csrf.js` | A new random token for each request, its cookie and its header |
| `lib/view.js` | The elements of the panel; every text the server sends is put in as text |
| `lib/i18n.js` | The labels in English and French |

## The roles of WAI-ARIA 1.3

The draft of WAI-ARIA 1.3 adds roles for annotations. With `aria_roles: true` in the bundle's configuration,
`ClientConfig` gives `ariaRoles` to the controller, which then writes them in the panel:

| Element | Without | With `ariaRoles` |
|---|---|---|
| A comment | Its author, date and text in the list item | The same, wrapped in an element of role `comment` |
| A suggestion | The state, `<del>` and `<ins>` side by side | The state, then an element of role `suggestion` that owns only the `<del>` and the `<ins>`; the word for a removal outside it |
| A passage | The quote | The quote, with `aria-details` naming the list of its thread's comments, `phil-comments-<thread>-comments` |

The option stays off until the draft becomes a recommendation and screen readers announce the roles; the markup is the
same otherwise. The editor's highlights go through the CSS Custom Highlight API, which adds no element, so a passage in
the editor carries no attribute.

## The stylesheet

`comments.css` styles the panel, the selection toolbar and the highlights (`::highlight(phil-comments)` and
`::highlight(phil-comments-active)`), on custom properties that take PHIL UX's tokens when its stylesheet is loaded,
the page's own colours otherwise.
