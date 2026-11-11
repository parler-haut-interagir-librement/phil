# The contrast rule

The tokens of phil-ux are tested against a contrast rule, and a site runs the same rule on its overrides.

## The rule

- **The palette**: every tone fits in sRGB; two tones whose numbers differ by 50 or more reach 4.5:1, by 40 or more
  3:1, whatever their hues.
- **The page**, in both schemes: the text, the muted text and the link reach 4.5:1 on the surface and on the raised
  surface; the surface reaches 4.5:1 on the text, for an inverted tooltip; the focus ring reaches 3:1 on both surfaces.
  The two highest levels of the activity calendar reach 3:1 on the surface, and two levels apart 3:1 on each other.
- **The grid**, for every role, in both schemes: `on-quiet` on `fill-quiet`, `on-loud` on `fill-loud` and `on-quiet` on
  both surfaces, for a ghost or outlined control and an error message, reach 4.5:1; `border-loud` and `fill-loud`, for
  the edge of a control, a gauge and a switch, reach 3:1 on both surfaces.

The ratios are those of WCAG 2.2, success criteria 1.4.3 and 1.4.11: the relative luminance of each colour, converted
from OKLCH through OKLab to linear sRGB and clipped to the gamut.

## Checking a site's overrides

```php
use Phil\UxCss\Contrast\ContrastRule;
use Phil\UxCss\Contrast\Pair;
use Phil\UxCss\Token\TokenSheet;

$sheet = TokenSheet::fromFiles(
    'vendor/phil/ux-css/css/tokens/palette.css',
    'vendor/phil/ux-css/css/tokens/colors.css',
    'assets/styles/colors.css',          // the site's overrides, read last
);

$violations = new ContrastRule([
    new Pair('--app-color-badge', '--phil-color-surface', ContrastRule::NON_TEXT),
])->check($sheet);

foreach ($violations as $violation) {
    echo $violation, "\n";               // --phil-color-link on --phil-color-surface (dark): 3.12:1, below 4.5:1
}
```

In a PHPUnit test, assert that the list is empty.

`TokenSheet` reads the declarations only, the later one of a name winning; it follows `var()`, their fallbacks and
`light-dark()`, the first value in the light scheme and the second in the dark one. A property that does not end on an
`oklch()` colour, or that loops, resolves to nothing, and a pair that needs it is reported.
