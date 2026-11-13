# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `WeasyPrintEngine` | The `PrintEngine` that calls the `weasyprint` command: `pdf()`, `version()`, `assertSupported()`, `MINIMUM_VERSION` |
| `LocalAssets` | The directory a document takes its images, fonts and ICC profiles from: `inline()` writes them into the document |
| `PdfOptions` | The standard, output intent, tags, forms and images of the PDF: `arguments()` gives the command's arguments |
| `PdfVariant` | The standards WeasyPrint writes, PDF/A, PDF/UA and PDF/X: `isPdfX()` |
| `Exception\WeasyPrintFailed` | A print that failed, with `exitCode` and `errorOutput` |
