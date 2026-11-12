# phil/forms-autosave

**A form's input kept while it is filled: drafts in the browser, restored with a notice, and on the server when asked.**

phil/forms-autosave keeps what a person types into a long form, so that a closed tab, a crash, an expired session or
a slip of the back button does not lose it. Each change is saved as a draft once the typing stops, in the browser's
local storage; when the form is shown again, the draft is put back with a notice that can discard it. Once the form is
sent and valid, the draft is forgotten. Passwords, files, CSRF tokens, hidden fields and the fields an application
marks sensitive are never kept.

It is for any Symfony application with forms that take time to fill: an application form, a long article, a
character sheet, a survey. One option turns it on, `autosave: true`. For signed-in users, the application can also
keep drafts on the server, so that a draft follows them to another device; the content is then sealed through
Symfony's KeyManagement component, and a draft saved meanwhile from another device or tab is told with a notice rather than
overwritten.

It is a Symfony bundle on the Form component, with a Stimulus controller served by AssetMapper, with no build step.
A draft's key is a keyed hash of the form, its subject and its user: neither the browser's storage, the URLs nor the
table tells whose draft it is or what it is about.

## Status

| Part | State |
|---|---|
| The option `autosave`: the key, the structure version, the settings and the labels on the form's tag; fields marked in or out | Implemented and tested |
| The `autosave` controller: drafts in local storage, debounced, restored with a notice, discarded, forgotten once sent | Implemented; its modules and the controller tested under Node |
| The status template, and the `AutosaveStatus` recipe of both PHIL UX kits | Implemented and tested |
| The server store: drafts per user, by revision, sealed through Symfony's KeyManagement component, conflicts told, expired drafts purged | Implemented and tested, on SQLite |
| The endpoints: a stateless JSON API behind the controller's header and CSRF token | Implemented and tested |
| UJO (Universal JSON Output) fields of `phil/block-editor-bundle` kept, their editor mounted again on restore | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the controller, the status, the server store. |
| [Configuration](doc/configuration.md) | Every option of the bundle. |
| [Forms](doc/forms.md) | The option `autosave`, keys and subjects, the structure version, the fields kept and left out, submissions. |
| [JavaScript](doc/javascript.md) | The controller, its values, its modules, its events, the notices and the live region. |
| [The server store](doc/server-store.md) | Users, revisions and conflicts, encryption, expiry and the purge command. |
| [Endpoints](doc/endpoints.md) | Each route, its body, its answers, the controller's header and its CSRF token. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions, and the answers of its endpoints. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the fuzz target and the mutation bar. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/forms-autosave
```

```php
$form = $this->createForm(ApplicationType::class, $application, ['autosave' => true]);
```

```twig
{{ form_start(form) }}
    {{ include('@PhilFormsAutosave/status.html.twig') }}
    {{ form_widget(form) }}
{{ form_end(form) }}
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-sodium`, Symfony 8.2, and Stimulus 3 in the browser. For the server
store: Doctrine DBAL 4 and ORM 3, the routing and CSRF components, a way to name the signed-in user — SecurityBundle
or a service of the application — and, unless encryption is turned off, `symfony/key-management` with a client and a
master key.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
