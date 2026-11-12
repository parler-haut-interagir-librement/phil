# Testing

## The bundle's tests

`tests/Unit` covers the keys, contents and drafts, `DraftKeys`, `Drafts` — saves by revision, conflicts and races,
expiry, drafts that no longer open, the size bound, discarding and purging —, the same contract on both stores (the
Doctrine one on SQLite in memory, its table created from the mapping, and rows that are no draft), the KeyManagement
sealer over the component's local KMS, the owner of Symfony Security, the form type extension — the tag's values, keys
by subject and user, the fields marked, invalid submissions, the endpoint's URL, submitted forms kept until the response
—, the structure version, the labels, the cookie of sent forms, every answer of the endpoints and the CSRF tokens they
accept and refuse, the purge command, and the bundle's configuration, services, prepended settings and compiler pass.

`tests/Functional` boots a kernel with FrameworkBundle in French, DoctrineBundle on SQLite, TwigBundle,
KeyManagementBundle with a local `sodium://` client and the bundle with its server store, and plays a form from its page
to its submission: the tag and the status template, drafts saved sealed, read, refused across users and without a token,
an invalid submission that keeps the draft, and a valid one that sets the cookie and deletes the server's copy.

`tests/js` runs the controller and its modules under Node's test runner, on a small stand-in for the DOM: the fields
kept and left out, values read and written back, editors mounted again, storage refused or full, drafts expired or of
another version, the cookie of sent forms, debouncing, CSRF tokens, the server client, the status and notice, and the
controller's saves, restores, discards, conflicts and pushes to the server.

`tests/Fuzz/draft.php` fuzzes the reader of stored drafts: whatever it reads back writes the same JSON, or is refused
with `InvalidDraft`.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/forms-autosave/tests
composer fuzz -- draft
node --import ./tests/js/register.mjs --test tests/js     # from packages/forms-autosave
```

Infection holds the changed code to 100 % of mutants killed.

## Testing an application

Set `server.store: memory` and `server.encrypt: false` in the `test` environment: drafts live in the process and are
emptied between requests. `Drafts` is public in the container, to seed or read drafts from a test.
