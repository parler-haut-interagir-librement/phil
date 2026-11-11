# Reference

Every class and module of the bundle, with its role.

| Class | Kind | Role |
|---|---|---|
| `Phil\BlockEditorBundle\PhilBlockEditorBundle` | `final class`, `AbstractBundle` | The bundle: the `phil_block_editor` configuration, the form theme and the AssetMapper paths |
| `Phil\BlockEditorBundle\Application\Toolset` | `final readonly class` | The block and inline tools of a field; the `full` and `reduced` presets |
| `Phil\BlockEditorBundle\Application\Upload\ImageUploader` | `interface` | The port that keeps uploaded images |
| `Phil\BlockEditorBundle\Application\Upload\ImageUpload` | `final readonly class` | An image as the request received it |
| `Phil\BlockEditorBundle\Application\Upload\UploadedImage` | `final readonly class` | Where an image is now: the block's `file` |
| `Phil\BlockEditorBundle\Application\Reference\ReferenceSearch` | `interface` | The port behind the `@` and `[[` menus |
| `Phil\BlockEditorBundle\Application\Reference\Suggestion` | `final readonly class` | A reference, its label, its address and a hint |
| `Phil\BlockEditorBundle\Application\Reference\DocumentReferences` | `final class` | The mentions, page links and files of a document |
| `Phil\BlockEditorBundle\Application\Exception\BlockEditorException` | `interface` | Every exception of the bundle |
| `Phil\BlockEditorBundle\Application\Exception\InvalidToolset` | `final class` | An unknown tool or preset, or no paragraph |
| `Phil\BlockEditorBundle\Application\Exception\InvalidSuggestion` | `final class` | A suggestion's reference or label of the wrong form |
| `Phil\BlockEditorBundle\Application\Exception\UploadRefused` | `final class` | An uploader's refusal, shown to the writer |
| `Phil\BlockEditorBundle\Infrastructure\DependencyInjection\BlockEditorServices` | `final class` | Declares the bundle's services |
| `Phil\BlockEditorBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | `final readonly class` | Stops the container with a message that says what to change when `framework.csrf_protection` is false |
| `Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers` | `final class` | One `phil/ujo` normalizer per toolset |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType` | `final class`, `AbstractType` | The editor as a form field |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorTransformer` | `final readonly class`, `DataTransformerInterface` | JSON checked and narrowed, to canonical JSON or `Document`, and back |
| `Phil\BlockEditorBundle\Infrastructure\Http\Endpoints` | `final readonly class` | The endpoints' URLs, when configured and routed |
| `Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest` | `final class` | Whether a request comes from the editor: its header, the browser's `Sec-Fetch-Site` and the stateless CSRF token `phil_block_editor` unless the method is safe; the name of the token's cookie |
| `Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController` | `final readonly class` | The upload endpoint |
| `Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController` | `final readonly class` | The two search endpoints |

| File | Role |
|---|---|
| `config/routes.php` | The three routes, imported by the application |
| `templates/form/block_editor.html.twig` | The form theme |
| `assets/src/editor_controller.js` | The Stimulus controller |
| `assets/src/lib/*.js` | Its modules ([javascript.md](javascript.md)) |
| `assets/styles/block-editor.css` | The stylesheet |
| `assets/package.json` | The controller declared for Symfony UX |

See [configuration.md](configuration.md), [forms.md](forms.md), [editor.md](editor.md) and
[integrations.md](integrations.md).
