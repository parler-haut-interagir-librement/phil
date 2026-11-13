# Security

The body of a `PrintableHtml` may hold text from users, even once sanitized, and WeasyPrint follows what a document
references: an image, a stylesheet, an attachment. A `<link rel="attachment" href="file:///etc/passwd">` would put that
file in the PDF. The engine confines the command on three sides.

## The command line

The command is a list of arguments given to `symfony/process`, which quotes each one, and nothing of the document
reaches it.

| Argument | Effect |
|---|---|
| `--allowed-protocols data` | WeasyPrint refuses every address that is not `data:`, `file:` and `http:` included, as well as those a `data:` image references in turn |
| `--base-url ''` | A relative address has nothing to resolve against |
| `--encoding utf-8` | The document is read as what it is |
| The options of `PdfOptions` | Each built from a checked value, such as `--pdf-variant=pdf/x-4`; see [PDF output](pdf-output.md) |
| `- -` | The document on standard input, the PDF on standard output: no temporary file to clean up |

A refused address is logged by WeasyPrint and left out of the page; the print goes on.

## No remote address

Remote images are refused: the engine loads nothing from the network, and no option turns that on. WeasyPrint's command
line can only allow a whole protocol, and allowing `https` would let a document call any host the server can reach,
those of its private network included. An application that needs a remote image fetches it itself, under its own rules,
and places it in the assets directory or in a `data:` address.

## The assets directory

`LocalAssets` is how a document takes files from the disk. It reads the document before the command starts, and writes
each file it recognizes into it as a `data:` address. The command never has the right to read the disk, so a reference
this class does not recognize is not read at all.

It looks at `src` of `img` and `embed`, `data` of `object`, `href` and `xlink:href` of an SVG `image`, and at the
`url()` of `style` elements and `style` attributes. A reference is taken when all of these hold:

| Rule | Refused |
|---|---|
| It is relative | `file:///etc/hosts`, `https://…`, `//host/…`, `/etc/hosts`, `\etc\hosts` |
| It has neither query nor fragment | `logo.png?x=1`, `sprite.svg#icon` |
| Its real path is inside the directory, once `..`, percent-encoding and symbolic links are resolved | `../secret.png`, a link that points outside |
| It is a regular, readable file | A directory, a device |
| Its extension is that of an image, a font or an ICC profile | `.env`, `.txt`, `.css`, `.html` |

The extensions taken are `avif`, `gif`, `icc`, `icm`, `jpeg`, `jpg`, `otf`, `png`, `svg`, `ttf`, `webp`, `woff` and
`woff2`.

Every file of those types in the directory can be printed by anyone who writes a document, so it holds public assets
and nothing else.

A stylesheet is not fetched from the directory: `PrintableHtml` carries the text of its stylesheets. An SVG file taken
from the directory cannot reference another file, as WeasyPrint refuses the address. An `@import`, or a bare string in
`image-set()`, stays unread for the same reason.

## Limits

| Limit | Default | When it is passed |
|---|---|---|
| The time of a print, `$timeout` of `WeasyPrintEngine` | 120 seconds | The command is stopped, and `WeasyPrintFailed` thrown |
| The size of a file, `$maxFileBytes` of `LocalAssets` | 10 MiB | `WeasyPrintFailed`, without printing |
| The total of a document, `$maxTotalBytes`, a file used twice counting twice | 50 MiB | `WeasyPrintFailed`, without printing |

The memory of the command is bounded by the system: a container limit, or `ulimit` in a wrapper script given as
`$binary` that ends with `exec weasyprint "$@"`. A document can still ask for a costly layout, and the timeout is what
stops it. The number of pages is not limited.

The environment of the PHP process is passed on to the command, as with any process Symfony starts. The command runs
no script of the document and cannot send anything out, since it fetches nothing.
