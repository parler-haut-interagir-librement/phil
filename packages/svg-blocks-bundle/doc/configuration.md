# Configuration

```yaml
phil_svg_blocks:
    secret: '%kernel.secret%'      # the secret drawings are signed with, 32 bytes or more
    qr_code:
        error_correction: M        # L, M, Q or H
    chart:
        width: 640                 # 100 to 4000 pixels
        height: 400
    math:
        max_length: 20000          # bytes
        max_depth: 64              # groups, arguments, environments and macros
        max_expansions: 1000       # uses of macros a formula expands
    mermaid:
        url: ~                     # http://mermaid:3000; none leaves diagrams to their code
        timeout: 10                # seconds
        async: true                # diagrams drawn in the background, not during the save
        documents: ~               # the service, a MarkupDocuments, of the background pass
    preview:
        enabled: true
        max_bytes: 65536
```

The `math` limits are those of `phil/latex-mathml`'s `LatexToMathMl`: a formula expands at most `max_expansions` uses
of its macros, and as many tokens as `max_length`.

Changing `secret` makes every kept drawing untrusted until its document is saved again: readers see the blocks' own
rendering meanwhile.

With `mermaid.async: false`, diagrams are drawn during the save, which then waits up to `timeout` for each diagram.

With phil/qr-code-bundle installed, its renderer draws the `qrCode` blocks with the defaults of its own
configuration — level, quiet zone, colours, size —, and `qr_code.error_correction` is left unused.

## Services

| Service | Class |
|---|---|
| `Phil\SvgBlocks\Signer` | the signer |
| `Phil\SvgBlocks\BlockMarkup` | the renderers of `qrCode`, `chart`, `math`, and `mermaid` when `mermaid.url` is set; public |
| `phil_svg_blocks.trusts` | the closure `Signer::trusts(...)` |
| `phil_svg_blocks.ujo_renderer` | the `HtmlRenderer` of `phil/ujo-bundle`, decorated |
| `Phil\SvgBlocks\Http\HttpMermaidService` | when `mermaid.url` is set, on the `http_client` service |
| `StoredMarkupTypeExtension` | with `phil/ujo-bundle` |
| `PreviewController`, `PreviewRequest` | when the preview is enabled and the CSRF component installed |
| `MarkupScheduler`, `RenderDeferredMarkupHandler` | with Messenger, when `mermaid.documents` names a service |

The container stops, with what to change, when the preview is enabled and `framework.csrf_protection` is false.
