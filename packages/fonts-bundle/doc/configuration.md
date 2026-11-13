# Configuration

```yaml
# config/packages/phil_fonts.yaml
phil_fonts:
    directory: '%kernel.project_dir%/public/fonts'
    public_path: /fonts
    font_display: swap
    licence_register: '%kernel.project_dir%/config/fonts/licences.json'
```

| Option | Default | What it sets |
|---|---|---|
| `directory` | `%kernel.project_dir%/public/fonts` | The directory of the font files, read with its folders |
| `public_path` | `/fonts` | The URL path the directory is served at, which the `@font-face` rules point to |
| `font_display` | `swap` | The `font-display` of the rules: `auto`, `block`, `swap`, `fallback` or `optional` |
| `licence_register` | `%kernel.project_dir%/config/fonts/licences.json` | The JSON file that records each font file once its licence is checked |

The directory need not be public: when the fonts are served by a CDN or copied by AssetMapper, `public_path` is the URL
they end up at. The register belongs in version control, beside the code that ships the fonts.

## Services

| Service | Public | Given |
|---|---|---|
| `Phil\FontsBundle\Application\FontFiles` | yes | Always, with `ext-brotli` when it is loaded |
| `Phil\FontsBundle\Application\FontCatalogue` | yes | Always |
| `Phil\FontsBundle\Application\FontFaces` | yes | Always |
| `Phil\FontsBundle\Application\Licences` | yes | Always |
| `phil:fonts:list`, `phil:fonts:licences`, `phil:fonts:css` | | Always |
| `Phil\FontsBundle\Infrastructure\Twig\FontsExtension` | no | When Twig is installed |
| `Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts` | yes | When `phil/paged-media` is installed |
| `Phil\FontsBundle\Infrastructure\Media\MediaFonts` | yes | When `phil/media-bundle` is installed and enabled |
