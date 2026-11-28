<!-- phil-dev source-sha256=1393f2ba19ba9b50f475a11c02bcdc247a1ee134ee773ee7c959e68974eb9375 -->
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

Les limites de `math` sont celles de `LatexToMathMl` de `phil/latex-mathml` : une formule développe au plus
`max_expansions` utilisations de ses macros, et autant de jetons que `max_length`.

Changer `secret` rend chaque dessin conservé non fiable jusqu'à ce que son document soit enregistré de nouveau. En
attendant, les lecteurs voient le rendu propre des blocs.

Avec `mermaid.async: false`, les diagrammes sont dessinés pendant l'enregistrement, qui attend alors jusqu'à `timeout`
pour chaque diagramme.

Quand phil/qr-code-bundle est installé, son moteur de rendu dessine les blocs `qrCode` avec les valeurs par défaut de
sa propre configuration (niveau, zone de silence, couleurs, taille), et `qr_code.error_correction` reste inutilisé.

## Services

| Service | Classe |
|---|---|
| `Phil\SvgBlocks\Signer` | le signataire |
| `Phil\SvgBlocks\BlockMarkup` | les moteurs de rendu de `qrCode`, `chart`, `math`, et de `mermaid` quand `mermaid.url` est réglé ; public |
| `phil_svg_blocks.trusts` | la fermeture `Signer::trusts(...)` |
| `phil_svg_blocks.ujo_renderer` | le `HtmlRenderer` de `phil/ujo-bundle`, décoré |
| `Phil\SvgBlocks\Http\HttpMermaidService` | quand `mermaid.url` est réglé, sur le service `http_client` |
| `StoredMarkupTypeExtension` | avec `phil/ujo-bundle` |
| `PreviewController`, `PreviewRequest` | quand l'aperçu est activé et le composant CSRF installé |
| `MarkupScheduler`, `RenderDeferredMarkupHandler` | avec Messenger, quand `mermaid.documents` nomme un service |

Le conteneur s'arrête, en indiquant quoi changer, quand l'aperçu est activé et que `framework.csrf_protection` vaut
false.
