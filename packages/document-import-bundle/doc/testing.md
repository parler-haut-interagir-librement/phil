# Testing

## The bundle's tests

`tests/Unit` checks the configuration — every option, every refusal — and the services it gives with and without the
media library, the scanner and Messenger, the routing of `ImportDocument`, builds the SVG policy from the
configuration, runs `DocumentImports` over the formats it accepts and refuses, the size of uploads, the screening,
the report title and the event, runs the queue and its handler over pending imports kept in memory, the directory
store over a temporary directory, the screening over the fake scanner of `phil/upload-scan`, the report fragment over
Twig, and `MediaLibraryStore` against the media library of `phil/media-bundle` over records and files kept in memory.
`tests/Functional` compiles the container the bundle gives, imports an HTML paste whose SVG image carries a script,
imports an RTF file through the configured style map with a listener of `DocumentImported`, and exports a document
the importer reads again; it then boots an application with FrameworkBundle, Messenger, TwigBundle and
`phil/upload-scan-bundle`, refuses a file holding the EICAR test string before it is parsed, queues a DOCX file whose
message carries its claim alone, imports it on the in-memory transport's message, reports a refused one by its claim,
and renders the report fragment.

From the monorepo root:

```sh
vendor/bin/phpunit packages/document-import-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection, like the libraries.

## Testing an application

Set `media.store: folder` in the test environment to keep the images out of the media library, and assert on the
blocks and the media of `Imported`: the addresses are the prefix followed by the file names, the same from one run
to the next. A listener of `DocumentImported` is tested by dispatching the event with an `Imported` the test builds.
With `async`, route `ImportDocument` to an `in-memory://` transport in the test environment, assert on the messages
it holds, and dispatch them again with a `ReceivedStamp` to run the handler.
