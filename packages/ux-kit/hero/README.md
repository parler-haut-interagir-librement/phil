# Hero

An image across the whole box, which stays a real image, with its text alternative, its sources and its preload, and,
over it, the words of the page on a veil dark enough for light text whatever the picture. The image is the main one of
the page by default.

```twig {"preview":true}
<twig:Hero src="/images/hall.jpg" alt="" :width="1600" :height="900" size="large">
    <h1>Welcome to the hall</h1>
</twig:Hero>
```

## Installation

::: installation

## Usage

```twig
<twig:Hero
    src="{{ asset('images/hall.jpg') }}"
    :srcset="{960: asset('images/hall-960.jpg'), 1920: asset('images/hall-1920.jpg')}"
    alt=""
    :width="1920"
    :height="1080"
>
    <h1>{{ 'home.title'|trans }}</h1>
    <p><a href="{{ path('program') }}">{{ 'home.program'|trans }}</a></p>
</twig:Hero>
```

`src`, `alt`, `width` and `height` are required. The image is a `Picture` of the `image` recipe, laid out
`full-width`, its `sizes` `100vw` unless given, covering the box, cropped by `position`; it takes `sources`, a tighter
crop for phones by its media query, `srcset`, `placeholder` and `preview`. `priority` is `true`: the image loads at once,
is fetched first and is preloaded through Symfony WebLink, as the main image of a page should. Set it to `false` for a
hero below the fold.

`size` says how tall the hero stands: `screen`, the height of the screen, by default; `large`, seven tenths of it;
`auto`, the height of its content, 20rem at least. `align` places the content at the `end` of that height, by default,
at its `start` or in its `center`. `id` and `class` reach the box.

## A background that stays an image

A background drawn by CSS is invisible to screen readers, never preloaded with its candidates and picked by no
`srcset`: the hero keeps an `img`, laid under its content by phil-ux. Its `alt` says what it shows when that matters to
the page, and is empty when the image only sets the scene.

## Accessibility

The content lies on a veil of `--phil-color-veil`, its text in `--phil-color-on-veil`, which reaches 9:1 over a white
picture and more over any other. The image covers its box whatever the width, `object-fit: cover`; give the subject of
the picture a `position` that keeps it in view on a phone. The content is the page's own: its heading, its links and
their order.
