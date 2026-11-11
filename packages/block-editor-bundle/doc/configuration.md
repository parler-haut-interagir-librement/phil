# Configuration

```yaml
# config/packages/phil_block_editor.yaml
phil_block_editor:
    preset: full              # full, or reduced for simple sites
    tools: ~                  # the block types offered, replacing those of the preset
    inline_tools: ~           # the inline tools offered, replacing those of the preset
    colors: [accent, muted, success, warning, danger]
    image_uploader: ~         # an ImageUploader service; none takes image addresses only
    mention_search: ~         # a ReferenceSearch service behind "@"
    link_search: ~            # a ReferenceSearch service behind "[["
    max_upload_bytes: 10485760
    access: IS_AUTHENTICATED  # the attribute callers of the endpoints must be granted; ~ for none
```

| Option | Default | Effect |
|---|---|---|
| `preset` | `full` | The toolset every field starts from |
| `tools` | the preset's | The block types offered, in any order; `paragraph` is required |
| `inline_tools` | the preset's | The inline tools offered, in any order |
| `colors` | five tokens | The colour tokens of the text colour tool, written `data-color="token"`; the site's stylesheet gives each its colour |
| `image_uploader` | none | The service id of an `ImageUploader` |
| `mention_search` | none | The service id of a `ReferenceSearch` for `@` |
| `link_search` | none | The service id of a `ReferenceSearch` for `[[` |
| `max_upload_bytes` | 10 MiB | The largest image the upload endpoint passes to the uploader. At least 1 |
| `access` | `IS_AUTHENTICATED` | The attribute the application's authorization checker must grant to call the endpoints; without SecurityBundle, every call is refused. `~` checks nothing: guard the routes with `access_control` |

An unknown tool or preset, or a toolset without `paragraph`, is refused when the container is built.

## Toolsets

| Block tool | UJO type | | Inline tool | Written as |
|---|---|---|---|---|
| `paragraph` | `paragraph` | | `bold` | `<b>` |
| `header` | `header`, levels 1 to 6 | | `italic` | `<i>` |
| `list` | `list`: bulleted, numbered, to-do, nested | | `underline` | `<u class="cdx-underline">` |
| `quote` | `quote`, with its author | | `strikethrough` | `<s class="cdx-strikethrough">` |
| `code` | `code`, with its language | | `inlineCode` | `<code class="inline-code">` |
| `alert` | `alert`: a callout | | `link` | `<a href>` |
| `toggle` | `toggle`, holding blocks | | `marker` | `<mark class="cdx-marker">` |
| `columns` | `columns`, 2 to 4, holding blocks | | `textColor` | `<span class="text-color" data-color>` |
| `table` | `table` | | `superscript`, `subscript` | `<sup>`, `<sub>` |
| `image` | `image` | | `mention` | `<a data-mention="kind:id">` |
| `delimiter` | `delimiter` | | `wikiLink` | `<a data-ref="kind:id">` |
| | | | `lineBreak` | `<br>` |

The `reduced` preset has `paragraph`, `header`, `list` and `quote`, with `bold`, `italic`, `link` and `lineBreak`.
A field may name its own toolset ([forms.md](forms.md)).

## Services

| Service | Built as |
|---|---|
| `Phil\BlockEditorBundle\Application\Toolset` | `Toolset::preset()` with `preset`, `tools` and `inline_tools` |
| `Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers` | on `phil/ujo-bundle`'s `BlockTypeRegistry` |
| `Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest` | on FrameworkBundle's CSRF token manager, the authorization checker, if any, and `access` |
| `Phil\BlockEditorBundle\Infrastructure\Http\Endpoints` | on the router, if any, and the endpoints whose service is configured |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType` | on `Ujo`, the normalizers, the toolset, the endpoints, `colors` and the request stack; a form type |
| `Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController` | on `image_uploader` and `max_upload_bytes`; public |
| `Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController` | on `mention_search` and `link_search`; public |

The registry comes from `phil/ujo-bundle`, so its `allow_raw` setting and any type an application adds there apply to
the editor's fields as well; the editor itself never writes a `raw` block.
