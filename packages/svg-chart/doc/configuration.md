# Configuration

`Chart::fromConfig(array $config)` reads the shape of a Chart.js configuration:

| Key | Read as |
|---|---|
| `type` | `bar`, `line`, `pie` or `doughnut` |
| `data.labels` | the labels, texts or numbers |
| `data.datasets[].label` | the name of a series in the legend |
| `data.datasets[].data` | one number or `null` per label |
| `data.datasets[].borderColor`, `.backgroundColor` | the colour of a series, `#rrggbb` |
| `data.datasets[].backgroundColor` as a list | one colour per slice of a pie or a doughnut |
| `options.plugins.title.text` | the title |

Every other option — animation, responsiveness, fonts, scales, plugins — sets how a browser draws the chart and is
ignored. Colours are `#rrggbb` only: names and functions are refused rather than guessed, and so is a `Dataset`
built directly with any other colour.

## Limits

| Limit | Value |
|---|---|
| Labels | 500 (`Chart::MAX_LABELS`) |
| Series | 24 (`Chart::MAX_DATASETS`) |
| A label, a series name or the title | 200 characters (`Chart::MAX_TEXT`) |

`InvalidChart` refuses a configuration that breaks a limit, misses its type or its data, or holds a value of the
wrong kind; its message names the path of the value, such as `data.datasets.0.data.3`.

A `Chart` may also be built directly, with `ChartType`, the labels and `Dataset` values.
