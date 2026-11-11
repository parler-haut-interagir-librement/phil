# phil/block-editor-bundle

**A block editor for Symfony forms, in Stimulus controllers, that writes UJO (Universal JSON Output) documents checked on the server.**

phil/block-editor-bundle gives a Symfony form a block editor in the manner of Notion. Writers add blocks from a slash
menu or a + button, turn one into another, move them by a handle or the keyboard, and nest them in toggles and
columns. They format text from a toolbar or with shortcuts, mention people with `@`, link pages with `[[`, upload
images, and paste from Word, Google Docs or any page without dragging its styles along. The editor reads and writes
UJO, the block document format of `phil/ujo`, into a hidden input. On submission `phil/ujo` checks that JSON and narrows
it to the field's tools. A reduced mode offers only paragraphs, headings, lists, quotes and links, for simple sites.
The bundle does not store documents or render them for readers; with `phil/edit-lock-bundle`, writers edit a document
together, one writer per block.

It is for any Symfony application whose writers compose pages, articles, wiki entries or forum posts as blocks. The
field is a form type like any other, so a document lands in an entity property as canonical JSON or as a `Document`.
For readers, `phil/ujo-bundle` renders it with `ujo_html`. The searches behind `@` and `[[`, and the storage of
uploaded images, are the application's own: the bundle defines the ports and the routes, and the application writes a
class for each.

It is a Symfony bundle on `phil/ujo` and `phil/ujo-bundle`. The editor is a Stimulus controller served by AssetMapper,
with no build step, no npm package to install and no framework beyond Stimulus. Notion is the model of the interaction.
Its inline sanitizer in the browser mirrors the one of `phil/ujo`, so what is pasted is cleaned by the same rules the
server applies.

## Status

| Part | State |
|---|---|
| `Toolset`: block and inline tools, `full` and `reduced` presets | Implemented and tested |
| `BlockEditorType`, `BlockEditorTransformer`, the form theme: JSON checked and narrowed by `phil/ujo` | Implemented and tested |
| `ToolsetNormalizers`: one `phil/ujo` normalizer per toolset | Implemented and tested |
| `ImageUploader`, `ReferenceSearch` ports; `ImageUploadController`, `ReferenceSearchController`, routes | Implemented and tested |
| `DocumentReferences`: the mentions, page links and files of a document | Implemented and tested |
| The editor controller: blocks, slash menu, handles, block menu, toolbar, shortcuts, paste, nesting, mentions, images, read-only mode, English and French | Implemented; pure modules tested under Node, the controller checked in a browser ([testing](doc/testing.md)) |
| Writing together: the `focus` event, and the `apply`, `remove` and `lock` events another controller dispatches | Implemented; `place()` tested under Node, checked in a browser with `phil/edit-lock-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the routes, the controller and the stylesheet. |
| [Configuration](doc/configuration.md) | Every option, the services, and the toolsets. |
| [Forms](doc/forms.md) | `BlockEditorType`, its options, the form theme, and how a submitted document is checked. |
| [The editor](doc/editor.md) | The blocks, the menus, the handles, the toolbar, the shortcuts, pasting, nesting, read-only mode. |
| [Integrations](doc/integrations.md) | Image uploads, the `@` and `[[` searches, a media library, usage tracking. |
| [JavaScript](doc/javascript.md) | The controller's configuration, its events, its modules, its labels and its stylesheet. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions, and the answers of its endpoints. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the mutation bar, and the checks made in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/block-editor-bundle
```

```php
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;

$builder->add('body', BlockEditorType::class);                         // the configured toolset
$builder->add('summary', BlockEditorType::class, ['preset' => 'reduced']);
```

```twig
{{ form_row(form.body) }}                 {# the editor, through the bundle's form theme #}
{{ article.body|ujo_html }}               {# for readers, with phil/ujo-bundle #}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `phil/ujo` and `phil/ujo-bundle`. In the browser, Stimulus 3, served by
`symfony/stimulus-bundle` and AssetMapper.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
