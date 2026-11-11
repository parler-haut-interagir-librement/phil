# Extension points

What an application implements, configures, overrides or listens to. Documents are checked by `phil/ujo`.

## Ports

### `ImageUploader`

`Phil\BlockEditorBundle\Application\Upload\ImageUploader` — where the images writers drop go, named by
`image_uploader`. See [Integrations](integrations.md#image-uploads).

| Method | Contract |
|---|---|
| `upload(ImageUpload $image): UploadedImage` | Reads the temporary file itself — what the browser said of it is never proof —, keeps it, and returns its URL, its size when known and the fields the application keeps; `UploadRefused`, whose message is shown to the writer, for a file it does not take |

### `ReferenceSearch`

`Phil\BlockEditorBundle\Application\Reference\ReferenceSearch` — the suggestions of the `@` menu (`mention_search`)
and of the `[[` menu (`link_search`).

| Method | Contract |
|---|---|
| `search(string $query, int $limit): iterable<Suggestion>` | Up to `$limit` suggestions for what was typed, possibly empty; each reference written `kind:identifier`; only what the writer may see, since the search runs under their session |

Without a service named, the endpoint of that port answers 404.

## Configuration and field options

| Point | How |
|---|---|
| The block types and inline tools | `preset`, `tools`, `inline_tools`, globally or per field — see [Configuration](configuration.md) |
| The colours of the text colour tool | `colors`: tokens the site's stylesheet colours |
| The largest upload | `max_upload_bytes` |
| Documents from elsewhere | The field's `keep_foreign_blocks` option: blocks outside the toolset kept and written back unchanged |
| Labels | The field's `messages` option, or the `messages` key: any label replaced by its key |
| The endpoints | The field's `upload_url`, `mentions_url` and `links_url`; `null` turns one off |

See [Forms](forms.md).

## Form theme and stylesheet

`@PhilBlockEditor/form/block_editor.html.twig` draws the widget; the row, the errors and the help come from the
application's own form theme, and `form_widget()` renders the editor alone. The stylesheet's custom properties are
the contract of its look: `--pbe-text`, `--pbe-accent` and the others, redefined on `.phil-block-editor` — see
[JavaScript](javascript.md#the-stylesheet).

## Events

| Event | Detail | What a listener does |
|---|---|---|
| `change` on the input | — | Autosave, a dirty-form warning |
| `phil--block-editor-bundle--editor:change` | `{json}` | Reads the value written |
| `phil--block-editor-bundle--editor:focus` | `{id}` | Follows the block the writer is in |

A collaboration controller dispatches `phil-block-editor:apply`, `phil-block-editor:remove` and
`phil-block-editor:lock` on the editor's element to place, remove and close blocks — see
[JavaScript](javascript.md#writing-together).
