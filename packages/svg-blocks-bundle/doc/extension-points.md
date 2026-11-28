# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Run the background pass | Implement `Application\Contract\MarkupDocuments` and name it in `mermaid.documents` |
| Draw another type, or draw one differently | Redefine the `Phil\SvgBlocks\BlockMarkup` service with renderers of your own |
| Call another diagram service | Define a `Phil\SvgBlocks\Contract\MermaidService` and give it to a `MermaidRenderer` |
| Leave a field's documents alone | `svg_blocks: false` on the field |
| Show the preview in your own markup | Listen to `phil--svg-blocks-bundle--preview:rendered` |
