# Extension points

What an application implements, configures or relies on.

## Ports written by the application

### `DraftOwner`

`Phil\FormsAutosave\Application\Contract\DraftOwner` — who fills the form (`server.owner`). Without one, the user of
Symfony Security when SecurityBundle is installed, and visitors only otherwise.

| Method | Contract |
|---|---|
| `current(): ?string` | An identifier stable across the user's sessions and devices, or null for a visitor |

## Ports with shipped adapters

### `DraftStore`

`Phil\FormsAutosave\Application\Contract\DraftStore` — where the server's drafts are kept. Shipped:
`DoctrineDraftStore` and `InMemoryDraftStore`; an application replaces it by aliasing the interface.

| Method | Contract |
|---|---|
| `find(string $owner, DraftKey $key): ?Draft` | The draft under an owner's hash and a key |
| `insert(Draft $draft): bool` | False when the owner already has a draft under that key |
| `replace(Draft $read, Draft $next): bool` | False when the stored revision is no longer the one read |
| `delete(string $owner, DraftKey $key): void` | |
| `purge(DateTimeImmutable $now): int` | Deletes the drafts expired at that moment, and tells how many |

### `DraftSealer`

`Phil\FormsAutosave\Application\Contract\DraftSealer` — how contents are sealed. Shipped: `KeyManagementSealer` and
`PlainSealer`; an application's own, aliased to the interface, takes their place.

| Method | Contract |
|---|---|
| `seal(string $content, string $context): string` | The sealed text, bound to the context |
| `open(string $sealed, string $context): ?string` | The content, or null when it cannot be opened in that context |

## Configuration

See [Configuration](configuration.md): the secret, the durations and sizes, and the server store.

## Templates and styles

`@PhilFormsAutosave/status.html.twig` can be overridden in `templates/bundles/PhilFormsAutosave/`, or replaced by the
recipe `AutosaveStatus` of a PHIL UX kit. Its classes, `phil-autosave`, `phil-autosave__status` and
`phil-autosave__notice`, are styled by `forms-autosave.css`, outside any layer, so that the application's rules win.

## Translations

The labels are translated in the domain `phil_forms_autosave`; the bundle ships English and French. An application
adds a locale with its own `translations/phil_forms_autosave.<locale>.*` file.

## What the bundle relies on

| | |
|---|---|
| The form's tag | Rendered by `form_start()`, which writes the root form's `attr` |
| Stimulus | The application loads the controller, through the StimulusBundle |
| FrameworkBundle's CSRF protection | On, for the server store; the token is stateless and needs no session |
| The event `kernel.response` | Where submitted forms are forgotten |
