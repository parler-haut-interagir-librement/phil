# Exceptions

Every exception implements `Phil\PrintPreflight\Exception\PrintPreflightException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidDocument` | `InvalidArgumentException` | A size that is not positive, a rectangle of negative width or height, an image without a pixel either way or in a frame of no size, a negative page bleed, a negative ink coverage or line width, a PDF colour space name the library does not know |
| `InvalidProfile` | `InvalidArgumentException` | A negative bleed or safety margin, a minimum resolution that is not positive, a target below the minimum, a maximum below the target, a minimum page count below one, a maximum below the minimum, a page multiple below one, a maximum ink coverage that is not positive, a negative minimum line width |

Each message names what is wrong and what was given, for example `A size must be positive; 0 × 297 mm given.` or
`The page count must be a multiple of at least 1; 0 given.`

A document that breaks a printer's rule is not an exception: it is a finding in the report.
