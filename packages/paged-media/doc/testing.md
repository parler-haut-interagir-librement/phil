# Testing

## The library's tests

The suite `tests/Unit` writes its own fonts and images byte by byte — `TrueTypeBuilder` and `PngBuilder` in
`tests/Unit/Fixture` — so it depends on no font file and no image library. `PdfReader` reads back what a test writes:
it checks the cross-reference table against each object and each stream against its declared length, and
decompresses the streams.

From the monorepo root:

```sh
vendor/bin/phpunit packages/paged-media/tests
```

The library is held to 100 % covered mutation score by Infection.

The tests check the structure of a PDF/A document, not its conformance. After a change to the writer, render a
document with a real TrueType font and run it through [veraPDF](https://verapdf.org), the reference validator:

```sh
verapdf --flavour 2u --format text document.pdf
```

The Gotenberg client is tested against `MockHttpClient`: `GotenbergServer` in `tests/Unit/Fixture` answers each
request with the next response queued and reads the multipart body back into fields and files, and
`SelfSignedCertificate` makes a signing certificate in the test with OpenSSL, never kept.

`PageRule` is tested on the text it writes. The engine that reads it is tested in its own package.

`tests/Integration` runs every route against a real Gotenberg 9, and is skipped without one:

```sh
docker run --rm -d -p 3011:3000 gotenberg/gotenberg:9
PHIL_GOTENBERG_URL=http://localhost:3011 vendor/bin/phpunit packages/paged-media/tests/Integration
```

The timestamp test also needs `PHIL_GOTENBERG_TSA`, the URL of an RFC 3161 authority Gotenberg can reach.

## Testing code that writes PDFs

Because a document is deterministic, the simplest test compares it with a reference file:

```php
self::assertStringEqualsFile(__DIR__.'/Fixtures/receipt.pdf', $receiptPdf->render($receipt));
```

To assert on the text instead, write the document with `compress: false`, or decompress its streams: each is preceded
by `/Filter /FlateDecode /Length n >>` and holds `n` bytes of zlib data.

## Testing code that calls Gotenberg

Give the client a `MockHttpClient` and assert on what it sends and what it reads:

```php
$client = new MockHttpClient(new MockResponse('%PDF-1.7', ['response_headers' => ['Content-Type' => 'application/pdf']]));
$gotenberg = new Gotenberg($client, 'http://gotenberg:3000');
```

The checks of the stages and of the order of a list run before any request, so a test of a list Gotenberg would refuse
needs no server: it expects `InvalidPipeline`.
