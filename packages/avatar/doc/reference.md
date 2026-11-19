# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Avatar\AvatarGenerator` | `final readonly class` | The entry point: `svg()`, `dataUri()`, `styles()` |
| `Phil\Avatar\AvatarOptions` | `final readonly class` | How an avatar is drawn: style, size, outline, palette, name, label, letters; `accessibleName()` |
| `Phil\Avatar\Seed` | `final readonly class` | The opaque identifier an avatar is drawn from |
| `Phil\Avatar\Random` | `final class` | The choices of an avatar, drawn from its seed: `int()`, `pick()`, `chance()` |
| `Phil\Avatar\Initials` | `final readonly class` | The initials of a name, by grapheme cluster: `of()`, `isEmpty()`, `isRightToLeft()` |
| `Phil\Avatar\Color` | `final readonly class` | An sRGB color: `fromHex()`, `toHex()`, `luminance()`, `contrastWith()` |
| `Phil\Avatar\Palette` | `final readonly class` | Backgrounds and inks, each background readable: `named()`, `names()`, `fromHex()`, `inkFor()` |
| `Phil\Avatar\Shape` | enum | `square`, `rounded`, `circle` |
| `Phil\Avatar\Canvas` | `final class` | What a style draws on, writing every element itself |
| `Phil\Avatar\Contract\AvatarStyle` | interface | A way of drawing avatars, under a name |
| `Phil\Avatar\Style\InitialsStyle` | `final readonly class` | The initials on a background |
| `Phil\Avatar\Style\ShapesStyle` | `final readonly class` | Circles, squares and triangles on a background |
| `Phil\Avatar\Style\PixelsStyle` | `final readonly class` | A mirrored grid of five by five squares |
| `Phil\Avatar\Exception\AvatarException` | interface | Every exception of the package |
| `Phil\Avatar\Exception\InvalidAvatar` | `final class` | A seed, color, palette, size, label or style out of bounds |
