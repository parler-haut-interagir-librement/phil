# Avatars

## Drawing one

`AvatarGenerator::svg($seed, $options)` draws the avatar of a seed as an SVG document; `dataUri()` gives the same
document as a `data:image/svg+xml,…` URI, percent-encoded, for the `src` of an `<img>`.

The seed is a `Seed` or a string: a stable opaque identifier of the application, such as the member's UUID or a random
value kept on their profile. Draw from a value that does not change when the member renames themselves, so that their
avatar stays theirs.

```php
$avatars = new AvatarGenerator();

$avatars->svg('0192f3c4-7b1e-7000-8000-00000000002a', new AvatarOptions(name: 'Ada Lovelace'));
```

## The options

| Option | Default | Means |
|---|---|---|
| `style` | `initials` | `initials`, `shapes`, `pixels`, or a style of the application. |
| `size` | `64` | The width and height in pixels, from 16 to 1024; the drawing is a vector on a 100 × 100 view box. |
| `shape` | `Shape::Circle` | `Shape::Circle`, `Shape::Rounded` (corners of a sixth of the side) or `Shape::Square`. |
| `palette` | `Palette::named('default')` | The backgrounds and inks: see [Palettes and contrast](palettes.md). |
| `name` | none | The name the initials come from, such as the member's display name. |
| `label` | none | The text naming the avatar to assistive technologies, such as "Avatar of Ada Lovelace"; 200 characters at most. |
| `maxLetters` | `2` | The most letters of the initials, from 1 to 3. |

## The styles

| Style | Draws |
|---|---|
| `initials` | The initials of the name, centred, in the ink of the higher contrast with a background of the palette. A name without letters — empty, emoji only — draws the shapes style. |
| `shapes` | A background and three circles, squares or triangles of the palette's other colors, placed, sized and turned by the seed. |
| `pixels` | A background and a grid of five by five squares, mirrored left to right, in the ink of the higher contrast. |

Each style draws from its own stream of the seed: the same seed gives unrelated avatars in two styles, and the same
avatar in one style whatever the size, outline or label.

## Accessibility

| The avatar has | It is written | Assistive technologies |
|---|---|---|
| A label, or else a name | `role="img" aria-label="…"`, with a `<title>` | Announce it as an image of that name. |
| Neither | `aria-hidden="true" focusable="false"` | Skip it, as they should beside a name already written out. |

Beside the member's name in a list, leave the label out and the avatar is decorative. Alone, as the link to a profile,
give it a label. Initials keep a contrast of 4.5:1 at least with their background, the WCAG AA level of normal text.

In an `<img>`, the `alt` attribute names the avatar instead: `alt=""` for a decorative one.

```twig
<img src="{{ avatar_uri }}" alt="" width="32" height="32">
```

## Showing it on a page

Inline SVG needs no request. Two avatars of the same seed, style and outline
on one page share the identifier of their clip, whose shape is the same; two different avatars never do.

A `data:` URI suits an `<img>` and a Content Security Policy that allows `img-src data:`. To serve avatars as files,
answer with `Content-Type: image/svg+xml` and a long cache lifetime: an avatar changes only with its seed or options.
