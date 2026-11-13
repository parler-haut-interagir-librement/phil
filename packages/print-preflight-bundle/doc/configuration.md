# Configuration

```yaml
# config/packages/phil_print_preflight.yaml
phil_print_preflight:
    default_profile: booklet
    profiles:
        booklet:
            trim_sizes: [a5]
            bleed: 3mm
            safety_margin: 6mm
            minimum_pages: 8
            maximum_pages: 64
            page_multiple: 4
            severities: { page-count.multiple: warning }
        poster:
            trim_sizes: [a4, 297x210mm]
            bleed: 0.125in
            colour_workflow: rgb
            ignore: [colour.spot]
```

`default_profile` is the profile used when a check names none; the first profile by default. Without any profile,
there is one, named `default`, with the library's defaults.

| Option of a profile | Default | Rule |
|---|---|---|
| `trim_sizes` | `[]` | The trim sizes the printer prints, each orientation on its own; empty for any |
| `uniform_trim_size` | `true` | Every page has the trim size of the first page |
| `bleed` | `3mm` | The bleed each page must carry |
| `safety_margin` | `5mm` | How far inside the trim edge content stays |
| `minimum_resolution` | `150` | Pixels per inch below which an image is an error |
| `target_resolution` | `300` | Pixels per inch below which an image is a warning |
| `maximum_resolution` | `null` | Pixels per inch above which an image is a notice |
| `minimum_pages`, `maximum_pages` | `1`, `null` | The fewest and most pages the printer binds |
| `page_multiple` | `1` | 2 for both sides of a sheet, 4 for saddle stitching |
| `colour_workflow` | `cmyk` | `cmyk`, or `rgb` for printers that convert themselves |
| `allow_spot_colours` | `false` | Whether the printer runs spot colour plates |
| `maximum_ink_coverage` | `300` | The total ink coverage, in percent, above which an image or a flat colour is an error, in a CMYK workflow |
| `minimum_line_width` | `0.25pt` | The thinnest line the printer reproduces; a thinner one is a warning |
| `allow_overprint` | `true` | Whether the printer honours overprint |
| `allow_transparency` | `true` | Whether the printer accepts live transparency, as PDF/X-4 does |
| `severities` | `{}` | `error`, `warning` or `notice` by rule code, in place of the default |
| `ignore` | `[]` | The codes of the rules not to report |

The rules and their codes are those of `phil/print-preflight` (its `doc/profiles.md` and `doc/checks.md`).

## Lengths and sizes

A length is a number and a unit: `mm`, `in`, `pt` or `px` (CSS pixels, 96 to the inch); `0` needs none. A size is
`a4`, `a5`, `letter`, or a width and a height with one unit, `148x210mm` or `8.5 x 11 in`. Both are read by
`Lengths`, case and spaces aside.

Every profile is built once when the container is built: a length the bundle cannot read, a profile the library refuses
— a target resolution below the minimum, a page multiple of 0 — or an unknown default profile throws
`InvalidConfigurationException` then, not at the first check.

## Services

| Service | Public | Given |
|---|---|---|
| `Phil\PrintPreflightBundle\Application\PrintProfiles` | yes | Always |
| `Phil\PrintPreflightBundle\Application\Preflighter` | yes | Always |
| `Phil\PrintPreflightBundle\Application\MeasuredDocument` | yes | Always, with the `FontResolver` when there is one |
| `phil:print-preflight:check` | | Always |
| `Phil\PrintPreflightBundle\Application\Port\FontResolver` | no | An alias of `CatalogueFontResolver` when `phil/fonts-bundle` is enabled |
| `Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages` | yes | When `phil/media-bundle` is installed |

`PdfLayout` is not a service: it wraps one document of the native writer, so the application creates it.
