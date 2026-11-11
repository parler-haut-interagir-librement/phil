# Extension points

| Point | How |
|---|---|
| A token | Set it in the site's own `:root`, outside any layer: it wins over the token layers |
| A whole scale | Set its multiplier: `--phil-scale`, `--phil-scale-space`, `--phil-scale-text`, `--phil-scale-radius` |
| A hue | Set its tones, each at the lightness its number says, then run the contrast rule |
| A role | Set `--phil-color-<role>-<part>` to another `light-dark()` of two tones |
| The scheme | `data-theme` on the root or on any element |
| One element's gap or measure | The layout class's own custom property: `--phil-stack-gap`, `--phil-center-measure`… |
| A tone of one's own | A class that sets the six `--phil-tone-*`, in a layer after `phil.components` or outside any layer |
| One button | `--phil-button-fill`, `--phil-button-fill-hover`, `--phil-button-fill-active`, `--phil-button-on`, `--phil-button-border` |
| The size of controls | `--phil-control-sm`, `-md`, `-lg`; or `--phil-control-size` on one element |
| A dialog's or sheet's width | `--phil-dialog-size` on the element |
| The carousel | `--phil-carousel-item`, `--phil-carousel-gap` |
| The colours of highlighted code | `--phil-hl-keyword`, `--phil-hl-string`, `--phil-hl-number`, `--phil-hl-comment`, `--phil-hl-name`, `--phil-hl-markup` |
| The headline | `--phil-reveal-caret`, the durations through the controller's values, `.isRevealToggle` for the pause button |
| Extra contrast pairs | `new ContrastRule([new Pair(…)])` |
| Some layers only | Import the source files of `css/` into the same layers, in the same order |
