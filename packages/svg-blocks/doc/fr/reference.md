<!-- phil-dev source-sha256=df1430e01ebb973a360d7ba7432ff941189f17034042de05a5536ac8fcb98f5f -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `BlockMarkup` | Dessine les blocs d'un document et conserve leurs dessins, signés ; produit l'aperçu d'un bloc. |
| `Rendered` | Ce qu'une passe a fait : le document, s'il a changé, les blocs en attente, les échecs. |
| `Signer` | Signe un dessin, et vérifie la clé d'un dessin conservé. |
| `Source` | Ce à partir de quoi le dessin d'un bloc est produit. |
| `Contract\MarkupRenderer` | Dessine les blocs d'un type. |
| `Contract\MermaidService` | Dessine un diagramme Mermaid en SVG. |
| `Renderer\QrCodeRenderer` | Dessine les blocs `qrCode` avec `phil/qr-code`. |
| `Renderer\ChartRenderer` | Dessine les blocs `chart` avec `phil/svg-chart`. |
| `Renderer\MathRenderer` | Convertit les blocs `math` avec `phil/latex-mathml`. |
| `Renderer\MermaidRenderer` | Dessine les blocs `mermaid` par un `MermaidService`, en assainissant le résultat. |
| `Http\HttpMermaidService` | Le service Mermaid en HTTP. |
| `Exception\SvgBlocksException` | Implémentée par chaque exception du paquet. |
| `Exception\RenderingFailed` | Un bloc qui ne peut pas être dessiné, avec une raison pour son auteur. |
| `Exception\InvalidConfiguration` | Un secret, une adresse ou une limite avec lesquels le paquet ne peut pas fonctionner. |
