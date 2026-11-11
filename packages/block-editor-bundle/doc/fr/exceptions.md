<!-- phil-dev source-sha256=290587ff3a8896359a37e1f68edac8abe2eae35785d8385570904164e5e87725 -->
# Exceptions

Chaque exception implémente `Phil\BlockEditorBundle\Application\Exception\BlockEditorException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidToolset` | `InvalidArgumentException` | Un jeu d'outils nomme un outil de bloc ou en ligne que l'éditeur n'a pas, un préréglage autre que `full` et `reduced`, ou omet `paragraph` — depuis la configuration, à la construction du conteneur, ou depuis les options d'un champ, à la construction du formulaire |
| `InvalidSuggestion` | `InvalidArgumentException` | Une `Suggestion` dont la référence n'a pas la forme `kind:identifier`, ou dont le libellé est vide ou dépasse 200 caractères |
| `UploadRefused` | `RuntimeException` | Levée par un `ImageUploader` pour refuser un fichier ; son message parvient au rédacteur |

Chaque message nomme ce qui ne va pas, par exemple
`The block editor has no block tool named "statblock"; the block tools are "paragraph", "header", …`.

Un document soumis que `phil/ujo` refuse n'est pas une exception du bundle : la transformation du champ échoue avec le
message de la bibliothèque ([forms.md](forms.md)).

## Les réponses des points de terminaison

| Point de terminaison | Statut | Corps |
|---|---|---|
| Téléversement d'image | 200 | `{"success": 1, "file": {…}}` |
| | 400 | `{"success": 0, "message": "No image was received."}` — pas de champ `image`, ou téléversement échoué |
| | 403 | `… "You may not use the editor."` — l'attribut `access` non accordé |
| | 403 | `… "The request does not come from the editor."` — sans l'en-tête de l'éditeur, ou depuis un autre site |
| | 404 | `… "Images cannot be uploaded here."` — aucun téléverseur configuré |
| | 413 | `… "The image is too large."` — au-delà de `max_upload_bytes`, ou de taille inconnue |
| | 422 | le message de `UploadRefused` |
| Recherche | 200 | `{"items": [...]}` |
| | 403, 404 | `{"items": []}` — l'attribut `access` non accordé, depuis ailleurs, ou aucune recherche configurée |

L'éditeur affiche son propre message traduit pour chaque échec, sauf 422, dont le message vient de l'application.
