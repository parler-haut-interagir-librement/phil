# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through the book it describes and the arguments of the writer.

| Point | How |
|---|---|
| The addresses of the chapters | The `$urls` closure of `Book`: each address found in the chapters is mapped before the writer reads it — the application's media addresses to the paths of the images it added, an absolute URL, or `null` to drop it |
| The words of the navigation | `NavigationLabels`, given to the book: the titles of the contents, landmarks and page list in the book's language |
| The table of contents | Built from the headings down to `$tocDepth`, or given as a list of `TocEntry` |
| The layout | `Rendition`, given to the book, and the `layout`, `viewport` and `pageSpread` of each chapter |
| The narration | A `MediaOverlay` per chapter, and the narrators and classes of `Narration`, given to the book |
| Fallbacks of audio and video | The `fallback` argument of `Asset::audio()` and `Asset::video()` |
| Older reading systems | `Book::withNcx()` adds the EPUB 2 table of contents |
| Where a fragment of HTML is cut into chapters | `HtmlSplitter::split()` at a heading level, or chapters built one by one |
| How UJO blocks are rendered | `UjoChapters` takes the `Normalizer` and the `HtmlRenderer` of `phil/ujo`: a renderer with the application's block renderers adds its own blocks to the book; `pageBreak` is always rendered as a page marker of the page list |
| Where temporary files go | The `$temporaryDirectory` of `EpubWriter` |
| Catching the library's errors | Every exception implements `Phil\Epub\Exception\EpubException` |

The XHTML the writer emits is always purified and well-formed: a renderer of the application's may write any HTML,
the book receives only what passes.
