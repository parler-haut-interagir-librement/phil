# Hotspots

An image with points of interest: a `Picture` of the `image` recipe and, over it, a list of links, each placed at its
point, for the plan of a venue, a map of a game or the parts of a product. No script and no inline style: each point is
placed by classes and by its data attributes.

```twig {"preview":true}
<twig:Hotspots
    src="/images/hall.png"
    alt="The plan of the hall"
    :width="800"
    :height="400"
    label="Rooms of the hall"
    :spots="[
        {x: 20, y: 30, label: 'Stage', href: '/rooms/stage'},
        {x: 72.5, y: 64, label: 'Bar', href: '/rooms/bar'},
    ]"
/>
```

## Installation

::: installation

## Usage

```twig
<twig:Hotspots
    src="{{ asset('images/hall.png') }}"
    alt="{{ 'hall.plan'|trans }}"
    :width="800"
    :height="400"
    label="{{ 'hall.rooms'|trans }}"
    :spots="rooms"
    caption="{{ 'hall.caption'|trans }}"
/>
```

`src`, `alt`, `width`, `height` and `label` are required. Each point takes `x` and `y`, where it stands as a
percentage of the width from the left and of the height from the top of the image, its words, `label`, and its address,
`href`. A point beyond the image stands on its edge; a point without words, without an address, with a `javascript:`,
`vbscript:` or `data:` address, or whose place is not a number from 0 up, is left out. The image takes `sources`,
`srcset`, `sizes`, `priority` and `placeholder` as for `Picture`, its sources of the same crop, on which every point
stands; `caption`, `credit`, `license` and `licenseHref` make a figure of it. `id` and `class` reach the box of the
image and its points.

## Where a point stands

A point is a `li` of class `isHotspot`, over the image, with two classes, `hasHotspotX20 hasHotspotY30`, which place it
to the nearest five per cent, and two attributes, `data-x="20" data-y="30"`, which phil-ux reads through a typed
`attr()` where the browser does, Chromium 133 and later: the point then stands exactly where it was asked. Elsewhere,
it stands to the nearest five per cent of the width and of the height, as a class can say it without an inline style,
which a strict Content Security Policy would drop. Place the points with that margin in mind: the centre of a room,
not its corner.

The place is physical, from the left and from the top, the same right to left: an image is not mirrored.

## Accessibility

The points are a list of links named by `label`, read after the image whose text alternative says what it shows; each
link says its words, never a mark alone. On a screen narrower than 36rem, and without the stylesheet, the points read
as a list under the image instead of over it. Each link is a pill of light text on a dark veil, which keeps its
contrast whatever the picture under it, and stands at least 24 pixels high (WCAG 2.5.8). Keep the words short: a pill
over the edge of the image may cover what lies beside it.
