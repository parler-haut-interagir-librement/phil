<!-- phil-dev source-sha256=24094bf9c54aeabac0bed9bae929590e54d55f8bcb313cd268cfa91f83647714 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-mbstring` | toutes | `parse()` mesure le texte qu'elle lit, et une erreur l'affiche |
| `ext-dom` | toutes | La lecture du XML de libphonenumber : l'importateur seulement, mais Composer l'exige avec la bibliothèque |
| `ext-ctype` | toutes | La vérification des chiffres des métadonnées, dans l'importateur |

```sh
composer require phil/phone
```

Les plans de numérotation sont fournis avec la bibliothèque, dans `data/` : rien n'est téléchargé à l'exécution.
