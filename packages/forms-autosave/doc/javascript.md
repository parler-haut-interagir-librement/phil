# JavaScript

## The controller

`assets/src/autosave_controller.js`, registered as `phil--forms-autosave--autosave`, runs on the form's tag, where the
option `autosave` puts it.

| Value | Default | |
|---|---|---|
| `key` | | The draft's key |
| `version` | | The version of the form's structure |
| `ttl` | `604800` | Seconds a draft is kept |
| `debounce` | `800` | Milliseconds after the last change before a save; never under 100 |
| `serverDebounce` | `5000` | Milliseconds after the last save before the draft is sent to the server; never under 1,000 |
| `maxBytes` | `262144` | How large a draft is kept |
| `endpoint` | | The URL of the server's draft; none keeps drafts in the browser |
| `csrf` | | The name of the CSRF cookie of the endpoint |
| `invalid` | `false` | The form comes back from an invalid submission |
| `labels` | English | The texts of the live region, the notice and its buttons |

When it connects, the controller forgets the drafts the server said were sent, reads the draft of its key, and, unless
the form comes back invalid, puts it back and shows the notice. With an endpoint, it then reads the server's draft:
put back when the browser had none; told as newer when it is another revision than the one this browser read and holds
other values. Each `input` or `change` saves once the typing stops; `submit` and `pagehide` save at once.

Its actions are those of the notice's buttons:

| Action | |
|---|---|
| `discard` | Puts back the values the page was shown with, removes the draft, here and on the server |
| `dismiss` | Hides the notice; after a conflict, the values in the form replace the newer draft at the next save |
| `useServer` | Puts the newer draft of the server in the form |

It dispatches `phil--forms-autosave--autosave:restored`, with the fields put back in `detail.fields`, and
`phil--forms-autosave--autosave:discarded`. Each field it changes receives a bubbling `change` event.

## The status

The live region is the target `status`, `role="status"` and `aria-live="polite"`: it says each save, a restore, a
discard, a conflict, a draft too large, or a browser that refuses storage. The notice is the target `notice`, with its
`message` and the buttons `useServer`, `discard` and `dismiss`, each shown only when it applies. The bundle's template
and the kits' `AutosaveStatus` draw them; without them, the controller adds its own at the top of the form.

## The modules

| Module | |
|---|---|
| `lib/fields.js` | Which fields are kept, their values read and written back, two sets compared, editors mounted again |
| `lib/storage.js` | Drafts in local storage under `phil-autosave:<key>`, every access guarded; the cookie of sent forms |
| `lib/debounce.js` | A step run once the changes stop, flushed or cancelled |
| `lib/csrf.js` | A new token for each request that changes a draft, in a header and a cookie |
| `lib/server.js` | `DraftClient`: read, save by revision, discard; a failed request answered, never thrown |
| `lib/ui.js` | The English labels, the page's labels merged, the status and notice built when the template has none |

## Local storage

A draft is stored as `{"v": 1, "version", "savedAt", "revision", "fields"}`. A private window, blocked storage or a
full quota leaves the form working without drafts, and the status says so. A draft of another version, older than
`ttl`, dated more than a minute ahead, or that is no draft at all is removed when read.
