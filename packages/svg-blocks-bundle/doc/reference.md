# Reference

Every class and module of the bundle, with its role.

| Class or module | Role |
|---|---|
| `PhilSvgBlocksBundle` | The configuration, the stateless token for FrameworkBundle, the controller's path for AssetMapperBundle. |
| `Application\Contract\MarkupDocuments` | The application's documents, for the background pass. |
| `Application\Message\RenderDeferredMarkup` | Asks for the pending blocks of a subject to be drawn. |
| `Application\Handler\RenderDeferredMarkupHandler` | Draws them and saves the document. |
| `Infrastructure\Messenger\MarkupScheduler` | Sends the message. |
| `Infrastructure\Form\StoredMarkupTypeExtension` | Draws the blocks of the documents of UJO fields. |
| `Infrastructure\Form\StoredMarkupTransformer` | The transformer it adds. |
| `Infrastructure\Http\PreviewController` | The preview endpoint. |
| `Infrastructure\Http\PreviewRequest` | Whether a preview request comes from this site. |
| `Infrastructure\DependencyInjection\SvgBlocksServices` | The services of the configuration. |
| `Infrastructure\DependencyInjection\StoredMarkupPass` | Decorates the UJO renderer, wires or removes the background pass, checks CSRF. |
| `assets/src/preview_controller.js` | The Stimulus controller of the preview. |
| `assets/src/lib/preview.js` | The block's data, the request, the answer, the pause. |
| `assets/src/lib/csrf.js` | The double-submit token. |
| `docker/mermaid` | The Mermaid service: Dockerfile, server, its browserless parts in `lib.mjs`, compose file. |
