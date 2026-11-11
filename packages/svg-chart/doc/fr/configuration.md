<!-- phil-dev source-sha256=dbe1faad63c12d018a03102c410486d0933ba159638186a08a8d00b45d3af0e9 -->
# Configuration

`Chart::fromConfig(array $config)` lit la forme d'une configuration Chart.js :

| Clé | Lue comme |
|---|---|
| `type` | `bar`, `line`, `pie` ou `doughnut` |
| `data.labels` | les libellés, textes ou nombres |
| `data.datasets[].label` | le nom d'une série dans la légende |
| `data.datasets[].data` | un nombre ou `null` par libellé |
| `data.datasets[].borderColor`, `.backgroundColor` | la couleur d'une série, `#rrggbb` |
| `data.datasets[].backgroundColor` sous forme de liste | une couleur par pointe d'un graphique circulaire ou en anneau |
| `options.plugins.title.text` | le titre |

Toutes les autres options — animation, adaptabilité, polices, échelles, extensions — règlent la façon dont un
navigateur dessine le graphique et sont ignorées. Les couleurs s'écrivent uniquement `#rrggbb` : les noms et les
fonctions sont refusés plutôt que devinés, tout comme un `Dataset` construit directement avec une autre couleur.

## Limites

| Limite | Valeur |
|---|---|
| Libellés | 500 (`Chart::MAX_LABELS`) |
| Séries | 24 (`Chart::MAX_DATASETS`) |
| Un libellé, un nom de série ou le titre | 200 caractères (`Chart::MAX_TEXT`) |

`InvalidChart` refuse une configuration qui dépasse une limite, qui n'a pas de type ou de données, ou qui contient une
valeur du mauvais genre ; son message nomme le chemin de la valeur, comme `data.datasets.0.data.3`.

Un `Chart` peut aussi être construit directement, avec `ChartType`, les libellés et des valeurs `Dataset`.
