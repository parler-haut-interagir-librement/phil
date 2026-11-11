<!-- phil-dev source-sha256=bc662a85d6dffb6fca56ba4f2b5808cca0fbaaaaa876cb6516d367e655c82003 -->
# Référence

Chaque classe et chaque module du bundle, avec son rôle.

| Classe | Genre | Rôle |
|---|---|---|
| `Phil\BlockEditorBundle\PhilBlockEditorBundle` | `final class`, `AbstractBundle` | Le bundle : la configuration `phil_block_editor`, le thème de formulaire et les chemins AssetMapper |
| `Phil\BlockEditorBundle\Application\Toolset` | `final readonly class` | Les outils de bloc et en ligne d'un champ ; les préréglages `full` et `reduced` |
| `Phil\BlockEditorBundle\Application\Upload\ImageUploader` | `interface` | Le port qui conserve les images téléversées |
| `Phil\BlockEditorBundle\Application\Upload\ImageUpload` | `final readonly class` | Une image telle que la requête l'a reçue |
| `Phil\BlockEditorBundle\Application\Upload\UploadedImage` | `final readonly class` | L'emplacement actuel d'une image : le `file` du bloc |
| `Phil\BlockEditorBundle\Application\Reference\ReferenceSearch` | `interface` | Le port derrière les menus `@` et `[[` |
| `Phil\BlockEditorBundle\Application\Reference\Suggestion` | `final readonly class` | Une référence, son libellé, son adresse et une indication |
| `Phil\BlockEditorBundle\Application\Reference\DocumentReferences` | `final class` | Les mentions, les liens de page et les fichiers d'un document |
| `Phil\BlockEditorBundle\Application\Exception\BlockEditorException` | `interface` | Chaque exception du bundle |
| `Phil\BlockEditorBundle\Application\Exception\InvalidToolset` | `final class` | Un outil ou un préréglage inconnu, ou l'absence de paragraphe |
| `Phil\BlockEditorBundle\Application\Exception\InvalidSuggestion` | `final class` | Une référence ou un libellé de suggestion de la mauvaise forme |
| `Phil\BlockEditorBundle\Application\Exception\UploadRefused` | `final class` | Le refus d'un téléverseur, présenté au rédacteur |
| `Phil\BlockEditorBundle\Infrastructure\DependencyInjection\BlockEditorServices` | `final class` | Déclare les services du bundle |
| `Phil\BlockEditorBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | `final readonly class` | Arrête le conteneur avec un message qui dit quoi changer quand `framework.csrf_protection` est à false |
| `Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers` | `final class` | Un normaliseur `phil/ujo` par jeu d'outils |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType` | `final class`, `AbstractType` | L'éditeur en champ de formulaire |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorTransformer` | `final readonly class`, `DataTransformerInterface` | Le JSON vérifié et restreint, vers du JSON canonique ou un `Document`, et retour |
| `Phil\BlockEditorBundle\Infrastructure\Http\Endpoints` | `final readonly class` | Les URL des points de terminaison, quand ils sont configurés et routés |
| `Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest` | `final class` | Détermine si une requête vient de l'éditeur : son en-tête, `Sec-Fetch-Site` du navigateur et le jeton CSRF sans état `phil_block_editor`, sauf si la méthode est sûre ; le nom du témoin du jeton |
| `Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController` | `final readonly class` | Le point de terminaison de téléversement |
| `Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController` | `final readonly class` | Les deux points de terminaison de recherche |

| Fichier | Rôle |
|---|---|
| `config/routes.php` | Les trois routes, importées par l'application |
| `templates/form/block_editor.html.twig` | Le thème de formulaire |
| `assets/src/editor_controller.js` | Le contrôleur Stimulus |
| `assets/src/lib/*.js` | Ses modules ([javascript.md](javascript.md)) |
| `assets/styles/block-editor.css` | La feuille de style |
| `assets/package.json` | Le contrôleur déclaré pour Symfony UX |

Voir [configuration.md](configuration.md), [forms.md](forms.md), [editor.md](editor.md) et
[integrations.md](integrations.md).
