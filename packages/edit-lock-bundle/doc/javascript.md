# JavaScript

## The controller

`assets/src/collaboration_controller.js` is a Stimulus controller, registered as
`phil--edit-lock-bundle--collaboration`. It is placed around the block editor of `phil/block-editor-bundle`:

```twig
<div data-controller="phil--edit-lock-bundle--collaboration"
     data-phil--edit-lock-bundle--collaboration-config-value="{{ collaboration|json_encode }}">
    {{ form_row(form.body) }}
    <aside data-phil--edit-lock-bundle--collaboration-target="presence"></aside>
    <p data-phil--edit-lock-bundle--collaboration-target="status" role="status"></p>
</div>
```

```php
// in the page's controller
$collaboration = $clientConfig->for('article:42', $holder, takeOver: $isOwner);
```

| Target | Role |
|---|---|
| `presence` | Where the roster is drawn: each writer, what they edit, and a *Take over* button per block when offered |
| `status` | A live region announcing a block held by someone else, a lease lost, a takeover |

The `config` value is what `ClientConfig::for()` gives, plus optional `locale` and `messages`:

| Key | Meaning |
|---|---|
| `resource`, `holder` | The resource, and the writer `{id, name}` this page writes as |
| `seconds` | The lease's duration; the controller beats every third of it |
| `takeOver` | Whether to offer the takeover of others' blocks; the server still asks the policy |
| `urls` | The endpoints; `null` turns leases off |
| `topic`, `hub` | The topic and the hub's subscription URL; `null` without Mercure |
| `csrfCookie` | The name of the cookie and header of the double-submitted CSRF token; `null` sends none, and the endpoints refuse every `POST` |
| `locale`, `messages` | The labels' language — the page's `lang` by default — and labels replaced by key |

## What it does

| When | The controller |
|---|---|
| The writer enters a block | Releases the block held before, if any, and acquires this one; a block someone else holds is announced |
| The editor writes a new value | Compares it with the last one, block by block, and saves the held block if it changed, or removes it if it is gone; a block the writer is in but no longer holds is asked for again first |
| Every third of the duration | Sends a heartbeat for the held block; without Mercure, asks for presence too |
| A presence message arrives | Closes in the editor the blocks others hold — every block under a lease on the whole resource —, draws the roster, and announces it when the held block was taken over |
| A save or a removal of another writer arrives | Hands it to the editor, which puts the block in place |
| The page closes | Gives up every lease of the writer on the resource, by a request that outlives the page |

Requests are sent one after the other, in the order the writer acted, so that a save never overtakes the lease it
needs. Nothing throws for the network: a request that fails is as if refused.

## With the block editor

The two controllers talk through DOM events on the editor's element, which `phil/block-editor-bundle` documents in
its own `doc/javascript.md`:

| Event | From | Detail | Role |
|---|---|---|---|
| `phil--block-editor-bundle--editor:focus` | the editor | `{id}`, `null` when the writer leaves the editor | The block the writer is in |
| `phil--block-editor-bundle--editor:change` | the editor | `{json}` | The document, after each change |
| `phil-block-editor:apply` | this controller | `{block, list, after}` | A block another writer saved |
| `phil-block-editor:remove` | this controller | `{id}` | A block another writer removed |
| `phil-block-editor:lock` | this controller | `{blocks: {id: name}}` | The blocks others hold, with their names |

The editor puts a saved block in place of its copy, or inserts it where it goes, without drawing the block being
edited again, and starts its undo history over, since undoing would bring back what the other writer replaced. A
held block is outlined, shows its holder's name and is `inert`: it cannot be focused or edited until it is free.

Granularity is the block. Two writers never type in the same block; a block that holds others — a toggle, columns — is
held, saved and applied with them.

## Modules

| Module | Role |
|---|---|
| `lib/leases.js` | The endpoints, called for one resource with a new CSRF token on each `POST`, each answer `{ok, status, body}` |
| `lib/csrf.js` | A new random token for each request, its cookie and its header |
| `lib/snapshot.js` | The document block by block — own fields, list, block before —, and the blocks added, changed or gone |
| `lib/presence.js` | Each lease's end on the page's clock, the blocks of others, the roster |
| `lib/streams.js` | The Turbo Streams of the bundle read into actions |
| `lib/i18n.js` | The labels, in English and French |

Every module but the controller runs without a browser and is tested under Node.

## Labels

`presence`, `editing`, `block`, `blocks`, `whole`, `you`, `takeOver`, `held`, `takenOver`, `lost`, `tookOver` and
`refused`, in `lib/i18n.js`; `{name}` and `{count}` are replaced. A locale such as `fr_CA` takes French, an unknown
one English; `messages` replaces any label by its key.

## The stylesheet

`assets/styles/edit-lock.css` styles the roster under `.phil-edit-lock-roster` with `--pel-text`, `--pel-muted`,
`--pel-border`, `--pel-accent`, `--pel-focus` and `--pel-radius`, which take PHIL UX's tokens when that stylesheet is
loaded and the page's `Canvas` and `CanvasText` otherwise. The look of a held block in the editor comes from the block
editor's own stylesheet.
