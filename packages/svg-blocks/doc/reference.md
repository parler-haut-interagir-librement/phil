# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `BlockMarkup` | Draws the blocks of a document and keeps their drawings, signed; previews one block. |
| `Rendered` | What a pass did: the document, whether it changed, the pending blocks, the failures. |
| `Signer` | Signs a drawing, and checks the key of a stored one. |
| `Source` | What a block's drawing is made from. |
| `Contract\MarkupRenderer` | Draws the blocks of one type. |
| `Contract\MermaidService` | Draws a Mermaid diagram as SVG. |
| `Renderer\QrCodeRenderer` | Draws `qrCode` blocks with `phil/qr-code`. |
| `Renderer\ChartRenderer` | Draws `chart` blocks with `phil/svg-chart`. |
| `Renderer\MathRenderer` | Converts `math` blocks with `phil/latex-mathml`. |
| `Renderer\MermaidRenderer` | Draws `mermaid` blocks through a `MermaidService`, sanitized. |
| `Http\HttpMermaidService` | The Mermaid service over HTTP. |
| `Exception\SvgBlocksException` | Implemented by every exception of the package. |
| `Exception\RenderingFailed` | A block that cannot be drawn, with a reason for its author. |
| `Exception\InvalidConfiguration` | A secret, an address or a limit the package cannot work with. |
