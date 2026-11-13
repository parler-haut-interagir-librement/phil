# Configuration

```yaml
# config/packages/phil_epub.yaml
phil_epub:
    temporary_directory: null
    storage:
        type: directory            # or media
        directory: '%kernel.project_dir%/var/epub'
        media_collection: null
    fonts:
        obfuscate: true
        directory: fonts
        stylesheet: css/fonts.css
```

| Option | Default | What it sets |
|---|---|---|
| `temporary_directory` | `null` | Where a book is written before it is read back, and uploaded from to the media library; the system's temporary directory when null |
| `storage.type` | `directory` | `directory`, or `media` to keep each book as a medium of `phil/media-bundle` |
| `storage.directory` | `%kernel.project_dir%/var/epub` | The directory of `directory` |
| `storage.media_collection` | `null` | The key of the media collection the books go to with `media`; none when null |
| `fonts.obfuscate` | `true` | Whether `EpubFonts` obfuscates the fonts it embeds, as many font licences for e-books ask |
| `fonts.directory` | `fonts` | The folder of the fonts in the book |
| `fonts.stylesheet` | `css/fonts.css` | The path of the stylesheet of their `@font-face` rules in the book |

## Services

| Service | Public | Given |
|---|---|---|
| `Phil\EpubBundle\Application\Epubs` | yes | Always |
| `Phil\EpubBundle\Application\Port\EpubStorage` | yes, an alias | `DirectoryStorage`, or `MediaLibraryStorage` with `type: media` |
| `phil:epub:write` | | Always |
| `Phil\EpubBundle\Infrastructure\Messenger\EpubHandler` | no | When Messenger is installed |
| `Phil\EpubBundle\Infrastructure\Fonts\EpubFonts` | yes | When `phil/fonts-bundle` is installed and enabled |
| `Phil\EpubBundle\Infrastructure\Media\MediaAssets` | yes | When `phil/media-bundle` is installed and enabled |
