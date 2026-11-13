# phil/epub-bundle

**E-books from a Symfony application: built from the application's records, written now or in a worker, kept where the
application keeps its files, with fonts it may embed.**

phil/epub-bundle puts `phil/epub` in a Symfony application. One service, `Epubs`, writes a book as an EPUB 3 file, to
bytes or to the storage — a directory, or the media library of `phil/media-bundle`. Book sources, registered by name
with an attribute, build a book from an identifier, so a Messenger message, `WriteEpub`, carries a name and an
identifier only, the book being built again in the worker; once kept, the book is announced by an event. A command
writes a book from a terminal. With `phil/fonts-bundle`, `EpubFonts` embeds families of the font directory, refusing a
face whose licence forbids embedding, obfuscating the others and declaring them in a stylesheet; with
`phil/media-bundle`, `MediaAssets` turns media into images, a cover with its alternative text, or fonts.

It is for any Symfony application that publishes long text: stories, rulebooks, manuals, collections of articles.

It is a bundle built on `symfony/config`, `symfony/dependency-injection`, `symfony/console` and
`symfony/filesystem`. Messenger, an event dispatcher, `phil/fonts-bundle` and `phil/media-bundle` are optional: each
integration is registered when its package is installed.

## Status

| Part | State |
|---|---|
| Configuration: temporary directory, storage (directory or media library), fonts | Implemented and tested |
| `Epubs`, `BookSource`, `#[AsBookSource]` and `BookSources` | Implemented and tested |
| `EpubStorage`, `DirectoryStorage`, `MediaLibraryStorage` | Implemented and tested |
| `WriteEpub`, `EpubHandler` and `EpubStored`, retried when the failure may pass | Implemented and tested |
| `phil:epub:write` | Implemented and tested |
| `EpubFonts` for `phil/fonts-bundle`, `MediaAssets` for `phil/media-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [Writing books](doc/writing.md) | `Epubs`, book sources, the storage and the command. |
| [Messenger](doc/messenger.md) | `WriteEpub`, the handler, the event, and what is retried. |
| [Fonts and media](doc/fonts-and-media.md) | `EpubFonts` and `MediaAssets`. |
| [Extension points](doc/extension-points.md) | The services and ports an application relies on or replaces. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, beside the library's exceptions. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/epub-bundle symfony/messenger
```

```php
#[AsBookSource('story')]
final readonly class StoryBooks implements BookSource
{
    public function __construct(private Stories $stories, private EpubFonts $fonts) {}

    public function build(string $id): Book
    {
        $story = $this->stories->get($id);

        return $this->fonts->embed(new Book($story->metadata())->withChapter(...$story->chapters()), 'Example Serif');
    }
}

$bus->dispatch(new WriteEpub('story', '42', 'stories/42.epub'));
```

```sh
bin/console phil:epub:write story 42 --output=var/story-42.epub
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/epub`; Symfony Messenger for the message; `phil/fonts-bundle` for `EpubFonts`;
`phil/media-bundle` for the media storage and `MediaAssets`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
