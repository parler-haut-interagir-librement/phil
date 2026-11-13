# Exceptions

Everything the package reports at print time is a `Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed`, a
`RuntimeException`.

| Property | What it holds |
|---|---|
| `exitCode` (`?int`) | The code the command ended with; `null` when it was stopped, could not start, or never ran |
| `errorOutput` (`string`) | What the command wrote on its error output, up to 64 KiB |

The message names the failure and leaves the document out. `errorOutput` can quote the addresses of the document:
log it where the application keeps such data.

| Situation | `exitCode` | Message |
|---|---|---|
| The command ended with an error | Its code | `WeasyPrint stopped with exit code N.` |
| It ran past the timeout | `null` | `"<binary>" ran out of time.` |
| It was killed, or could not be started | `null` | `"<binary>" did not run to its end: …` |
| It ended well without a PDF on its output | `0` | `WeasyPrint did not write a PDF.` |
| `--version` gave no number | `0` | `WeasyPrint did not say which version it is.` |
| `assertSupported()` on an older command | `null` | `WeasyPrint X is older than the Y this package needs.` |
| The assets directory is missing | `null` | `The assets directory "…" does not exist.` |
| A file is over `$maxFileBytes` | `null` | `The asset "…" is over the limit of N bytes.` |
| The files are over `$maxTotalBytes` | `null` | `The assets of the document are over the total limit.` |

A timeout or a limit below 1, and an option of `PdfOptions` out of range, throw `InvalidArgumentException` when the
object is built: a mistake in the wiring.

A command that cannot be found ends with the exit code 126 or 127 of the shell that starts it.
