# Testing

## The bundle's tests

`tests/Unit` covers the toolsets and presets, the suggestions and uploaded images, `DocumentReferences`, the
normalizers of each toolset, the transformer, the form type and its options, the endpoints — the `access` attribute
granted or refused, trusted requests, the CSRF tokens accepted and refused, URLs, every answer of the upload and search controllers — and the bundle's
configuration, services and prepended Twig, CSRF and AssetMapper settings. `tests/Functional` boots a kernel with
FrameworkBundle, SecurityBundle with a lazy firewall open to anonymous visitors, TwigBundle, `phil/ujo-bundle` and the
bundle, renders the field and its label through the form theme, and calls the endpoints through the routes, signed in
or not, with and without their token and its cookie. Installed
on its own, where the event dispatcher is a development requirement, the functional suite is skipped.

`tests/js` runs the pure modules under Node's test runner: the sanitizer against the output of `phil/ujo`, pasted
HTML of pages, Word and Google Docs, the document model, the tools and conversions, the menus, the keyboard, the
labels and the double-submitted CSRF token.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/block-editor-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/block-editor-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## Checks in a browser

The controller and the two modules that need the DOM — `caret.js` and `view.js` — are checked by hand in a browser,
on a page with Stimulus, the stylesheet and a document holding a heading, a paragraph, a nested list, a toggle,
columns and a block of another type. Each check below states what to do and what must happen.

| Area | Do | Expect |
|---|---|---|
| Opening | Load the page | Every block drawn; the foreign block as a card naming its type; in read-only mode no gutter |
| Splitting | At the end of a paragraph, Enter | A new paragraph below, the caret in it, the placeholder shown |
| Slash menu | Type `/tit`, ↓, Enter | The menu lists the headings, the second becomes the block, the caret in it |
| Marks | In a new paragraph type `-`, a space, `one`, Enter, Tab, `two` | A bulleted list, `two` nested under `one` |
| Leaving a list | Enter twice in an empty nested item | The item moves out, then the list ends with a paragraph after it |
| Toolbar | Double-click a word, click B, click the link button, type an address, Enter | The word bold and linked, the toolbar above the selection, the input's JSON holding `<b><a href="…">` |
| Block menu | Click a handle, ↓ to *Quote*, Enter | The menu opens on its first entry, the arrows move in it, the block becomes a quote with an author field |
| Undo | ⌘Z | The quote is a paragraph again |
| Drag | Drag a block of the toggle by its handle onto the empty column | The block moves into the column and is selected; the toggle shows its empty button |
| Mentions | Type ` @a` with a mention search answering two people | A menu of the two after *Searching…*, Enter inserts `@Ada Lovelace` as a mention, typing goes on after it |
| Paste | Paste HTML with a heading, a list and a bold span | A heading, a list and a paragraph with `<b>` after the current block |
| Selection | Escape, ⌘⇧↓, ⌘D, Backspace | The block outlined, moved down, duplicated, the copy deleted and the neighbour selected, *Block deleted.* announced |
| Images | Insert an image block, paste an address, Insert; or drop a file with an uploader | The image shown with its caption field; while uploading, *Uploading…* |
| Saving | Submit the form | The input holds the document without empty blocks |
| Writing together | With `phil/edit-lock-bundle`, two writers on one page; one types in a paragraph, then adds one | The other sees the paragraph closed with the writer's name, its text changing, the new paragraph inserted after it, the caret of their own block untouched |

## Testing a form that holds a document

Submit the JSON the editor would write, and assert on the canonical form:

```php
$form = static::getContainer()->get('form.factory')->create(ArticleType::class);
$form->submit(['body' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>Hi</strong> <mark>x</mark>"}}]}']);

self::assertTrue($form->isValid());
self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>Hi</b> x"}}]}', $form->getData()->body); // reduced toolset
```

To test an uploader or a search, call it directly, or the endpoint with the editor's header:

```php
$client->request('GET', '/_block-editor/mentions?q=ad', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1']);
```
