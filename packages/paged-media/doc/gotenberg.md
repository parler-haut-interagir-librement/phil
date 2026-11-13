# Gotenberg

`Gotenberg\Gotenberg` is a client of [Gotenberg](https://gotenberg.dev) 9, built on Symfony HttpClient.
It sends the PDF stages — in a list on `/forms/pdf/pipe`, or one alone on its own route —, the LibreOffice conversion,
and the routes that read a PDF. Every request is checked before it is sent: a stage's options when the stage is built,
the order of a list when the list is built, the files when the request is written. An answer that is not a success
becomes an exception.

The Chromium routes are not covered: HTML and CSS are laid out by a paged-media engine of the application's choice.

## The client

```php
use Phil\PagedMedia\Gotenberg\BasicAuth;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Symfony\Component\HttpClient\HttpClient;

$gotenberg = new Gotenberg(HttpClient::create(), 'http://gotenberg:3000');
$gotenberg = new Gotenberg($httpClient, 'https://pdf.example.com/gotenberg', new BasicAuth('app', $password), timeout: 120);
```

The URL is Gotenberg's base URL, with its root path when it runs behind one (`--api-root-path`). It is a service
address of the application's network: absolute, `http` or `https`, with a host — a bare name such as `gotenberg`, an IP
address and `localhost` included — and without credentials, which go in `BasicAuth`. It is read by the WHATWG URL
parser of PHP's URI extension; anything else is refused with `InvalidRequest`. `BasicAuth` matches
a Gotenberg started with `--api-enable-basic-auth`. The timeout bounds each request, in seconds; without one, the HTTP
client's own applies. Gotenberg's own limit (`--api-timeout`, 30 seconds by default) still holds: raise both for long
documents.

`version()` answers the version of the Gotenberg reached, such as `9.1.0`.

## Files

A request works on `Files`: files uploaded under `files`, ZIP archives Gotenberg unpacks, and URLs it downloads first.

```php
use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;

$files = Files::of(
    InputFile::fromPath('/var/data/1-report.pdf'),          // read in chunks when the request is sent
    InputFile::fromContents('2-annex.pdf', $annexBytes),     // held in memory
    InputFile::fromDocument('3-receipt.pdf', $document),     // a Document of the native writer, rendered now
)
    ->withArchive(InputFile::fromPath('/var/data/scans.zip'))
    ->withDownload(new Download('https://files.example.com/a.pdf', ['Authorization' => 'Bearer …']));
```

Names matter. Gotenberg takes the files in the alphanumeric order of their names — `merge` appends them in that order
—, names its results after them, and a stage names the files it needs by their names. A name holds no slash, quote or
line break. A file a stage needs, such as an image to stamp, is given to the stage, which uploads it beside the PDFs;
Gotenberg does not process it as a PDF. The same file named twice is sent once; two different files of the same name
are refused with `InvalidRequest`.

A download is a public `http` or `https` address whose server answers with a `Content-Disposition` header that names
the file. The address is read by `phil/url`, which refuses credentials, an IP address and a bare host name such as
`storage`: Gotenberg fetches it from inside the network it runs in, and a document must not lead it to a service that
is not public. A file of the application's own network is uploaded instead. Gotenberg's operator also decides which
hosts it may reach (`--api-download-from-allow-list`, `--api-download-from-deny-private-ips`); a URL it blocks answers
403.

## Producing files

```php
$result = $gotenberg->pipe($files, new Pipeline(new Merge(), new Optimize()));
$result = $gotenberg->run($files, new Rotate(90, 'odd'));              // one stage, on /forms/pdf/rotate
$result = $gotenberg->convert($documents, new LibreOfficeOptions(), new Pipeline(new Remediate(Profile::PdfA3b)));
```

`pipe()` posts the list as the `stages` field; `run()` posts the stage alone to its route, its options as form fields —
a text as it is, a list or an object as JSON. [Stages](stages.md) lists every stage and the order rules,
[LibreOffice](libreoffice.md) the conversion.

`RequestOptions` sets the headers of a request that produces files:

```php
use Phil\PagedMedia\Gotenberg\RequestOptions;

$gotenberg->pipe($files, $pipeline, new RequestOptions(outputFilename: 'facture-été', trace: 'order-42'));
```

`outputFilename` names the result without extension: Gotenberg adds the right one. `trace` is the request's identifier
in Gotenberg's logs, sent back in `Result::$trace`.

## The result

A `Result` is one file — a PDF, an image, a text — or a ZIP of several, when several PDFs come out or a `split` ran.

| Member | What it holds |
|---|---|
| `contents()` | The bytes, read whole |
| `saveTo($path)` | Writes the bytes to a file as they arrive, without holding them in memory |
| `$contentType`, `isZip()` | `application/pdf`, an image type, `text/plain`, or `application/zip` |
| `$filename` | The name from `Content-Disposition`: `report.pdf`, `report.docx.pdf`, `report_1.png` |
| `$residual`, `$rolledBack`, `conforms()` | After `remediate`: the violations left and the fixes undone ([Conformance](conformance.md)) |
| `$trace`, `$version` | The request's identifier and the version of Gotenberg that answered |
| `$deferred` | True when a webhook receives the result: the body is empty |

## Webhooks

A `Webhook` makes Gotenberg answer 204 at once, upload the result to a URL, then post an event to another. Both are
service addresses, as Gotenberg's own URL is: the application usually receives them on its own network.

```php
use Phil\PagedMedia\Gotenberg\Webhook;
use Phil\PagedMedia\Gotenberg\WebhookMethod;

$result = $gotenberg->pipe($files, $pipeline, new RequestOptions(trace: 'order-42', webhook: new Webhook(
    'https://app.example.com/pdf/upload',
    'https://app.example.com/pdf/events',
    WebhookMethod::Put,
    ['Authorization' => 'Bearer …'],
)));
// $result->deferred === true
```

The upload carries the result's `Content-Type`, `Content-Disposition`, `Gotenberg-Trace` and remediation headers; the
event is a JSON `POST` that `WebhookEvent::fromJson()` reads:

```php
$event = WebhookEvent::fromJson($request->getContent());
$event->success;          // webhook.success or webhook.error
$event->correlationId;    // the trace of the request
$event->errorStatus;      // with webhook.error: the status the request would have answered
$event->errorMessage;
```

The read routes answer JSON directly and take no webhook.

## Failures

A request refused before it is sent throws `InvalidStage`, `InvalidPipeline` or `InvalidRequest`. An answer that is
not a success throws a `GotenbergFailure`, which keeps the HTTP status — `0` when Gotenberg could not be reached —, the
message Gotenberg wrote, and the request's trace:

| Status | Exception | `isRetryable()` |
|---|---|---|
| 400, 405, 413, 415, other 4xx | `RequestRefused` | no |
| 401, 403 | `AccessDenied` | no |
| 404 | `RouteUnavailable`: the engine is not in the image, or its routes are disabled | no |
| 429, 503, no answer | `ServiceUnavailable` | yes |
| 409, 500, other 5xx | `ConversionFailed` | no |
| 200 with JSON of another shape | `UnexpectedResponse` | no |

```php
try {
    $gotenberg->run($files, new Select('9'));
} catch (RequestRefused $refused) {
    // "Gotenberg answered 400: Stage 'select' failed on 'report.pdf': invalid page range: page 9 out of range [1, 3]"
}
```

[Exceptions](exceptions.md) lists them with the library's others.
