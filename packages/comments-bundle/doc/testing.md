# Testing

## The bundle's tests

`tests/Unit` covers anchors and their limits, the text of inline HTML and a passage replaced in it, finding a passage
again — in place, moved, among equal places, by field, block and document, by context, to another occurrence when its
own has gone, beyond the basic plane, with contexts of wide characters —, threads on passages and on the page, comments
and their states, members never named by an email address, `Comments` with its policy, its events, re-anchoring that
leaves the page alone and suggestions applied to a UJO document, the texts of a UJO document and their paths, the same
contract on both stores (the Doctrine one on SQLite in memory, its tables created from the mapping, each entity read
back from the database) and the store in memory emptied between requests, every answer of the endpoints and the CSRF
tokens they accept and refuse, `ClientConfig` with the roles of WAI-ARIA 1.3, the component of the page for each kind
of viewer and its controller's refusals, the names of the kit's recipes, and the bundle's configuration, services,
compiler passes and prepended settings — the paths of AssetMapper in AssetMapperBundle's extension, nothing without
it.

`tests/Functional` boots a kernel with FrameworkBundle, DoctrineBundle on SQLite and the bundle, and plays two
writers and an editor on one page through the routes: a thread with a suggestion, a reply, refusals, the list,
re-anchoring after an edit, the suggestion applied, a moderated comment, reopening and deleting, each request with
its CSRF token and cookie; requests without them are refused. The same kernel, with TwigBundle, TwigComponentBundle and
the recipes of the PHIL UX kit copied where `ux:install` puts them, draws an article with `PhilComments:Page` and plays
a conversation under it by its plain forms only — open, reply, edit, resolve, moderate, reopen, delete —, then nobody
signed in, a form from another site, a changed address, an empty text, an unknown action, a missing thread and a
refused edit, and the roles of WAI-ARIA 1.3 turned on. Installed on its own, where the event dispatcher is a
development requirement, the functional suite is skipped.

`tests/js` runs the pure modules under Node's test runner: characters, the text of inline HTML, replacements, finding
a passage again with the same cases as the PHP tests, field paths, DOM points and offsets on small fake nodes,
suggestions applied to a document, requests and their CSRF token, a comment on the page, the panel's elements —
threads on the page, orphaned threads, the form of the page, the roles of WAI-ARIA 1.3 written only when asked —, and
labels.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/comments-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/comments-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## Benchmarks

`tests/Benchmark/AnchoringBench.php` measures finding a passage again in a document of a hundred blocks — in place,
moved to another block, gone —, in a field of a hundred thousand characters where a four-letter passage occurs at
every place, overlapping, and in a field of fifty thousand two-byte characters, and the texts of a document of a
hundred blocks. Without a coverage driver, a passage in the document costs about a tenth of a millisecond, the
document's texts a few milliseconds, and the hostile fields a few hundred milliseconds, growing with their length.

```sh
composer bench -- packages/comments-bundle/tests/Benchmark
```

## Fuzzing

The target `reanchoring` reads inputs nobody wrote: the first line a passage, the rest inline HTML cut into fields at
each blank line. Reading the HTML, finding the passage and replacing it never throw but for the passages an anchor
refuses; a passage found is the passage itself, at its offset, in its field, and replacing it by itself leaves the
text of its field as it was.

```sh
composer fuzz -- reanchoring --budget=60
```

## Checks in a browser

The controller is checked by hand on a page with Stimulus, the block editor, the controller and the routes. Each
check states what to do and what must happen.

| Area | Do | Expect |
|---|---|---|
| Selection | Select two words in a paragraph | The toolbar appears under them; selecting across two paragraphs shows nothing |
| Thread | *Comment*, write, send | The thread appears in the panel and the words are highlighted |
| Suggestion | Select a word, *Suggest*, change the replacement, send | The panel shows the word struck out and the replacement |
| Following | Type words before the highlighted passage | The highlight stays on the passage |
| Gone | Delete the passage | Its thread says it is orphaned and moves to the end of the panel |
| Elsewhere | Delete the passage while the same words remain in another block, save and re-anchor | The thread follows the other words, highlighted there |
| Page | Write in *Comment on the page*, send | The thread appears under *On the page*, the form empties; it has no quote and no highlight |
| No editor | A page with the controller and no editor | The section of the page and its form; threads on passages show their quote, only those the server orphaned say so |
| Navigation | Click a highlighted word, then a thread's passage | The thread is outlined; the editor scrolls to the passage |
| Accepting | *Accept* a suggestion | The passage is replaced in the editor, the thread resolved; saving the form keeps the change |
| Emoji | Comment a word after an emoji | The highlight covers exactly the word |
