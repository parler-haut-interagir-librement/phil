<!-- phil-dev source-sha256=528e6060a34b7761915808c620adf88e02c6aaa5c18b415979f5addb662894ee -->
# Mise en forme

`assets/styles/text-diff.css` met en forme une différence à l'intérieur d'un élément doté de la classe `text-diff`, ou
sous le contrôleur `comparison`, et nulle part ailleurs :

| Quoi | Comment |
|---|---|
| `ins` | Fond et texte verts, souligné |
| `del` | Fond et texte rouges, barré |
| `.text-diff-block` | Un bloc de la différence de deux documents, avec une bordure du côté où il commence : verte quand il est ajouté, rouge quand il est retiré, bleue quand il est modifié, ambre avec un fond quand il est déplacé |
| `[data-text-diff-mode="old"]` | Cache les insertions et les blocs ajoutés |
| `[data-text-diff-mode="new"]` | Cache les suppressions et les blocs retirés |
| `.text-diff-current` | Un contour autour du changement courant |
| `[aria-pressed="true"]` | Le bouton de la vue affichée, en gras |

Les insertions et les suppressions se distinguent par leur décoration autant que par leur couleur : aucun changement ne
dépend de la couleur seule.

## Thèmes

Les couleurs sont des propriétés personnalisées, claires par défaut et sombres quand le système le préfère, sauf si un
ancêtre porte `data-theme="light"` ou `data-theme="dark"` ; sur papier, ce sont les couleurs claires, et le contour
disparaît. Chacune respecte le contraste WCAG AA pour du texte normal sur le fond où elle est affichée.

| Propriété | Rôle |
|---|---|
| `--text-diff-inserted`, `--text-diff-inserted-background` | Les insertions et les blocs ajoutés |
| `--text-diff-deleted`, `--text-diff-deleted-background` | Les suppressions et les blocs retirés |
| `--text-diff-moved`, `--text-diff-moved-background` | Les blocs déplacés |
| `--text-diff-changed` | La bordure des blocs modifiés |
| `--text-diff-focus` | Le contour du changement courant |

Une page redéfinit n'importe laquelle d'entre elles sur `:root`, ou sur une seule différence :

```css
.revision-history {
    --text-diff-inserted-background: #dff6dd;
}
```
