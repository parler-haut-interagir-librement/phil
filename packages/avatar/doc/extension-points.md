# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a style, or replace one of the package's | Implement `Contract\AvatarStyle`, and pass it to `AvatarGenerator` |
| Draw in the colors of the application | Build a `Palette` and pass it in `AvatarOptions` |
| Label avatars in the language of the page | Pass the translated `label` in `AvatarOptions` |

## A style of the application

A style has a name — 1 to 32 lowercase letters, digits or `-` — and draws on a `Canvas` with the choices of a `Random`
stream that the seed and the style's name fix:

```php
use Phil\Avatar\Canvas;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

final readonly class RingsStyle implements AvatarStyle
{
    public function name(): string
    {
        return 'rings';
    }

    public function draw(Canvas $canvas, Random $random): void
    {
        $background = $random->pick($canvas->palette->backgrounds);
        $canvas->background($background);
        $ink = $canvas->palette->inkFor($background);

        for ($ring = 3; $ring >= 1; --$ring) {
            $canvas->circle(50.0, 50.0, $ring * 14.0, 1 === $ring % 2 ? $ink : $background);
        }
    }
}

$avatars = new AvatarGenerator([new RingsStyle()]);
```

The canvas is 100 by 100 units. It draws a background, circles, rectangles turned around their centre, polygons and a
centred line of text, and writes each element itself: a style cannot put markup of its own in an avatar. Its `palette`
and `initials` are those of the options. A style of the same name as one of the package's replaces it.

A style draws the same avatar for the same seed only if it takes every choice from the `Random` it is given: `int()`,
`pick()` and `chance()`, never `random_int()` or the time.
