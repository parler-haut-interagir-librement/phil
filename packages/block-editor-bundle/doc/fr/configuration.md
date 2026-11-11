<!-- phil-dev source-sha256=75bf0e5dcb8f48ac9807c26ea2048a55bf1ccc72feade3ce47f12af06e849463 -->
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

| Option | Valeur par défaut | Effet |
|---|---|---|
| `preset` | `full` | Le jeu d'outils de départ de chaque champ |
| `tools` | ceux du préréglage | Les types de blocs offerts, dans n'importe quel ordre ; `paragraph` est obligatoire |
| `inline_tools` | ceux du préréglage | Les outils en ligne offerts, dans n'importe quel ordre |
| `colors` | cinq jetons | Les jetons de couleur de l'outil de couleur du texte, écrits `data-color="token"` ; la feuille de style du site donne sa couleur à chacun |
| `image_uploader` | aucune | L'identifiant de service d'un `ImageUploader` |
| `mention_search` | aucune | L'identifiant de service d'un `ReferenceSearch` pour `@` |
| `link_search` | aucune | L'identifiant de service d'un `ReferenceSearch` pour `[[` |
| `max_upload_bytes` | 10 Mio | La plus grande image que le point de terminaison de téléversement transmet au téléverseur. Au moins 1 |
| `access` | `IS_AUTHENTICATED` | L'attribut que le vérificateur d'autorisations de l'application doit accorder pour appeler les points de terminaison ; sans SecurityBundle, tout appel est refusé. `~` ne vérifie rien : protégez les routes par `access_control` |

Un outil ou un préréglage inconnu, ou un jeu d'outils sans `paragraph`, est refusé à la construction du conteneur.

## Jeux d'outils

| Outil de bloc | Type UJO | | Outil en ligne | Écrit sous la forme |
|---|---|---|---|---|
| `paragraph` | `paragraph` | | `bold` | `<b>` |
| `header` | `header`, niveaux 1 à 6 | | `italic` | `<i>` |
| `list` | `list` : à puces, numérotée, de tâches, imbriquée | | `underline` | `<u class="cdx-underline">` |
| `quote` | `quote`, avec son auteur | | `strikethrough` | `<s class="cdx-strikethrough">` |
| `code` | `code`, avec son langage | | `inlineCode` | `<code class="inline-code">` |
| `alert` | `alert` : un encadré | | `link` | `<a href>` |
| `toggle` | `toggle`, qui contient des blocs | | `marker` | `<mark class="cdx-marker">` |
| `columns` | `columns`, 2 à 4, qui contiennent des blocs | | `textColor` | `<span class="text-color" data-color>` |
| `table` | `table` | | `superscript`, `subscript` | `<sup>`, `<sub>` |
| `image` | `image` | | `mention` | `<a data-mention="kind:id">` |
| `delimiter` | `delimiter` | | `wikiLink` | `<a data-ref="kind:id">` |
| | | | `lineBreak` | `<br>` |

Le préréglage `reduced` comprend `paragraph`, `header`, `list` et `quote`, avec `bold`, `italic`, `link` et `lineBreak`.
Un champ peut nommer son propre jeu d'outils ([forms.md](forms.md)).

## Services

| Service | Construit |
|---|---|
| `Phil\BlockEditorBundle\Application\Toolset` | par `Toolset::preset()` avec `preset`, `tools` et `inline_tools` |
| `Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers` | sur le `BlockTypeRegistry` de `phil/ujo-bundle` |
| `Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest` | sur le gestionnaire de jetons CSRF de FrameworkBundle, le vérificateur d'autorisations, s'il y en a un, et `access` |
| `Phil\BlockEditorBundle\Infrastructure\Http\Endpoints` | sur le routeur, s'il y en a un, et les points de terminaison dont le service est configuré |
| `Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType` | sur `Ujo`, les normaliseurs, le jeu d'outils, les points de terminaison, `colors` et la pile de requêtes ; un type de formulaire |
| `Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController` | sur `image_uploader` et `max_upload_bytes` ; public |
| `Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController` | sur `mention_search` et `link_search` ; public |

Le registre vient de `phil/ujo-bundle` : son réglage `allow_raw` et tout type qu'une application y ajoute s'appliquent
donc aussi aux champs de l'éditeur. L'éditeur lui-même n'écrit jamais de bloc `raw`.
