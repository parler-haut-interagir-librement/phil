# Testing

| Area | What is covered |
|---|---|
| `WeasyPrintEngine` | The arguments given to the command, the document on standard input, the PDF on standard output, the exit code and the error output of a failure, its bound, an answer that is not a PDF, the timeout, a missing command, the version read and compared |
| `PdfOptions` | Each option and its argument, their order, every output intent accepted and refused, PDF/X without a profile, the bounds of the resolution and the JPEG quality |
| Activation | The single line of `services.yaml` read and compiled by Symfony, the engine under the port with its defaults, a `LocalAssets` service given to it by autowiring |
| `LocalAssets` | Each element and attribute read, the stylesheets in every quoting, percent-encoding, every reference left alone — parent directories, links out of the directory, absolute paths, addresses, queries, fragments, NUL bytes, directories, extensions not taken, unreadable files —, the limits, a document left byte for byte when nothing is taken |

The unit tests, under `tests/Unit`, run a shell script that stands in for the command and keeps its arguments and its
input. They need no WeasyPrint and a POSIX shell.

```sh
vendor/bin/phpunit packages/paged-media-weasyprint/tests/Unit
```

The test under `tests/Integration` runs the real command, found on the `PATH`, and is skipped without it. It prints a
page, checks the version, checks that a file outside the assets directory reaches the PDF neither as an image nor as an
attachment, that a remote image is not fetched, and that a PDF/X-4 has its output intent, trim box and bleed box; that
last test takes a CMYK profile of the system, and is skipped without one.

The references of a document are also fuzzed, from the monorepo root:

```sh
composer fuzz -- assets
```
