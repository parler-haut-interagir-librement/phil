# Layout

The layout classes say what the element is. They hold no colour and read their gaps from tokens, each through a
custom property of its own that a site sets on one element.

| Class | What it is | Its custom properties |
|---|---|---|
| `isStack` | A column of children with the same gap, their margins removed | `--phil-stack-gap` (`md`) |
| `isCluster` | A row that wraps, the same gap both ways, centred on the cross axis | `--phil-cluster-gap` (`sm`) |
| `isGrid` | As many columns as fit, each at least the minimum wide | `--phil-grid-min` (`--phil-panel-sm`), `--phil-grid-gap` (`md`) |
| `isCenter` | A block centred in its container, no wider than a readable line | `--phil-center-measure`, `--phil-center-gutter` |
| `isNarrow` | No wider than the narrow measure, 36ch | `--phil-center-measure` |
| `isSplit` | Two columns from 48rem, stacked below, the first child first | `--phil-split-gap` (0) |
| `isFullHeight` | At least the height of the screen, `100dvh` | |
| `isCover` | A column with padding; its `isCoverBody` child takes the space left and centres its content | `--phil-cover-gap`, `--phil-cover-padding` |
| `isBleed` | Media that fills its cell, the image covering the box; 40 % of the screen's height below 48rem | `--phil-bleed-min-size` |
| `hasEntrance` | On an `isBleed`: enters with a slanted clip from 48rem, never under reduced motion | |

## A split page

```html
<div class="isSplit isFullHeight">
    <div class="isCover">
        <header><a href="/" class="isLogo"><img src="/logo.svg" alt="Studio"></a></header>
        <main class="isCoverBody">
            <div class="isNarrow">…</div>
        </main>
    </div>
    <figure class="isBleed hasEntrance">
        <picture>
            <source type="image/webp" srcset="/images/main.webp">
            <img src="/images/main.png" alt="">
        </picture>
    </figure>
</div>
```
