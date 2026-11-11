<!-- phil-dev source-sha256=dc8c33642973acd797f7269a1891ed11e7b66c1f906b42d6627ebb6293c743f4 -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou écoute. Les documents sont vérifiés par `phil/ujo`.

## Ports

### `ImageUploader`

`Phil\BlockEditorBundle\Application\Upload\ImageUploader` — la destination des images que déposent les rédacteurs,
nommée par `image_uploader`. Voir [Intégrations](integrations.md#téléversements-dimages).

| Méthode | Contrat |
|---|---|
| `upload(ImageUpload $image): UploadedImage` | Lit lui-même le fichier temporaire — ce que le navigateur en a dit n'est jamais une preuve —, le conserve et retourne son URL, sa taille si elle est connue et les champs que l'application garde ; `UploadRefused`, dont le message est présenté au rédacteur, pour un fichier qu'il n'accepte pas |

### `ReferenceSearch`

`Phil\BlockEditorBundle\Application\Reference\ReferenceSearch` — les suggestions du menu `@` (`mention_search`) et du
menu `[[` (`link_search`).

| Méthode | Contrat |
|---|---|
| `search(string $query, int $limit): iterable<Suggestion>` | Jusqu'à `$limit` suggestions pour ce qui a été tapé, possiblement aucune ; chaque référence écrite `kind:identifier` ; seulement ce que le rédacteur peut voir, puisque la recherche s'exécute sous sa session |

Sans service nommé, le point de terminaison de ce port répond 404.

## Configuration et options de champ

| Point | Comment |
|---|---|
| Les types de blocs et les outils en ligne | `preset`, `tools`, `inline_tools`, globalement ou par champ — voir [Configuration](configuration.md) |
| Les couleurs de l'outil de couleur du texte | `colors` : des jetons que colore la feuille de style du site |
| Le plus grand téléversement | `max_upload_bytes` |
| Les documents venus d'ailleurs | L'option `keep_foreign_blocks` du champ : les blocs hors du jeu d'outils sont conservés et réécrits sans changement |
| Les libellés | L'option `messages` du champ, ou la clé `messages` : tout libellé remplacé par sa clé |
| Les points de terminaison | `upload_url`, `mentions_url` et `links_url` du champ ; `null` en désactive un |

Voir [Formulaires](forms.md).

## Thème de formulaire et feuille de style

`@PhilBlockEditor/form/block_editor.html.twig` dessine le widget. La rangée, les erreurs et l'aide viennent du thème de
formulaire de l'application, et `form_widget()` produit le rendu de l'éditeur seul. Les propriétés personnalisées de la
feuille de style forment le contrat de son apparence : `--pbe-text`, `--pbe-accent` et les autres, redéfinies sur
`.phil-block-editor` — voir [JavaScript](javascript.md#la-feuille-de-style).

## Événements

| Événement | Détail | Ce que fait un écouteur |
|---|---|---|
| `change` sur le champ | — | Enregistrement automatique, avertissement de formulaire modifié |
| `phil--block-editor-bundle--editor:change` | `{json}` | Lit la valeur écrite |
| `phil--block-editor-bundle--editor:focus` | `{id}` | Suit le bloc où se trouve le rédacteur |

Un contrôleur de collaboration émet `phil-block-editor:apply`, `phil-block-editor:remove` et `phil-block-editor:lock`
sur l'élément de l'éditeur pour placer, enlever et fermer des blocs — voir
[JavaScript](javascript.md#écrire-à-plusieurs).
