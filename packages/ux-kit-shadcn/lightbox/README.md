# Lightbox

An image enlarged in a modal dialog: its thumbnail in a button that opens the native `dialog` by the invokers of HTML,
`command="show-modal"` and `commandfor`; Escape, its close button and a click on its backdrop, `closedby="any"`, close
it, and the browser gives the focus back to the thumbnail. Its Stimulus controller, `lightbox`, stands in only where
the browser runs neither the invokers nor `closedby`.

```twig {"preview":true}
<twig:Lightbox id="bridge" src="/images/bridge-480.jpg" alt="A bridge at dusk" :width="480" :height="360" :full="{src: '/images/bridge.jpg', width: 1600, height: 1200}" openLabel="Enlarge" />
```

## Installation

::: installation

## Usage

```twig
<twig:Lightbox
    id="bridge"
    src="{{ asset('images/bridge-480.jpg') }}"
    alt="A bridge at dusk"
    :width="480"
    :height="360"
    :full="{src: asset('images/bridge.jpg'), width: 1600, height: 1200}"
    openLabel="{{ 'image.enlarge'|trans }}"
    closeLabel="{{ 'dialog.close'|trans }}"
/>
```

`id`, `src`, `alt`, `width` and `height` are required. The thumbnail takes the props of a `Picture`, `srcset`, `sizes`,
`sources`, `aspect`, `fit` and `position`; `full`, the image of the dialog, takes `src`, `width`, `height`, `srcset`,
`sizes` and `sources`, its candidates sized to the width of the screen unless it has `sizes`. Without `full`, the
thumbnail is shown whole in the dialog. `deferred`, `placeholder` and `preview` serve the thumbnail, as for `Image`.

## Examples

### With a caption

The caption, the credit, the license and the creation show under the thumbnail and under the full image.

```twig
<twig:Lightbox id="map" src="/images/map-480.png" alt="The map of the city" :width="480" :height="360" caption="Where we meet" credit="Drawing: Ana Lima" />
```

### With a long description

`description` follows the thumbnail in a native disclosure, outside its button, which it describes through
`aria-describedby`: a reader hears it without opening the dialog.

```twig
<twig:Lightbox id="plan" src="/images/plan-480.png" alt="The plan of the hall" :width="480" :height="360" description="{{ 'hall.plan.description'|trans }}" />
```

### A frame, pixel art and a view transition

`frame` and `transition` are those of the thumbnail, as for `Image`; the full image is shown bare. `pixelated` keeps the
pixels sharp on both, where enlarging matters most.

```twig
<twig:Lightbox id="map" src="/images/map-16.png" alt="The map of the first level" :width="256" :height="256" :full="{src: '/images/map-16.png', width: 1024, height: 1024}" frame="framed" :pixelated="true" />
```

## Accessibility

The button is named by the image's text alternative, then by `openLabel`, read by screen readers alone; the dialog is
named by the text alternative, or by the caption when the image is decorative. The full image loads lazily, when the
dialog opens. The close button reads `closeLabel`. Every text is a prop, for the application to translate.
