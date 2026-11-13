# Conformance

Gotenberg repairs a PDF to a standard with the `remediate` stage, and checks it with the `validate` route. Both take a
`Profile`.

## Profiles

| Profile | Standard |
|---|---|
| `PdfA1a`, `PdfA1b` | PDF/A-1, on PDF 1.4 |
| `PdfA2a`, `PdfA2b`, `PdfA2u` | PDF/A-2: transparency and layers |
| `PdfA3a`, `PdfA3b`, `PdfA3u` | PDF/A-3: embedded files of any type |
| `PdfA4`, `PdfA4e`, `PdfA4f` | PDF/A-4, on PDF 2.0; `PdfA4f` needs an embedded file |
| `PdfUa1`, `PdfUa2` | PDF/UA, for accessibility |
| `FacturX` | Factur-X (ZUGFeRD), a PDF/A-3 electronic invoice |

The letter says what a PDF/A keeps: `b` its look, `u` its text as Unicode too, `a` its structure too, through tags.
Two profiles go together when one is a PDF/A or Factur-X profile and the other the PDF/UA profile of its PDF version:
PDF/A-1 to PDF/A-3 and Factur-X with `PdfUa1`, PDF/A-4 with `PdfUa2`. `Profile::checked()` refuses any other list.

## Remediate

```php
$result = $gotenberg->pipe($files, new Pipeline(
    new Remediate([Profile::PdfA2b, Profile::PdfUa1], language: 'fr-CA', figureAltText: 'Illustration'),
    new Optimize(),
));

$result->residual;     // the violations left: 0 means every document conforms to every profile
$result->rolledBack;   // the fixes undone because they lost pages, text or images
$result->conforms();   // true when the residual is 0
```

Gotenberg answers 200 even when violations remain: read the residual, and list what is left with `validate()`.
`aggressive: true` allows fixes that may change how pages look, under the same rollback; on PDF/A-1 it deletes every
embedded file.

A Factur-X remediation embeds the invoice XML given, at the level the XML declares, or repairs the invoice the PDF
already carries:

```php
new Remediate(Profile::FacturX, xml: InputFile::fromPath('factur-x.xml'), level: FacturXLevel::En16931);
```

The XML and its level go together, and only with `FacturX`; a document type, `INVOICE` by default, goes with the XML.

A remediation comes after the stages that change the content: only `encrypt`, `optimize`, `sign`, `timestamp` and a
terminal stage may follow it, and a PDF/A or Factur-X cannot be encrypted.

## Validate

```php
$reports = $gotenberg->validate(Files::of($pdf), [Profile::PdfA3b, Profile::PdfUa1]);

$report = $reports['invoice.pdf'];
$report->compliant;    // true only when the PDF conforms to every profile
$report->violations;   // list<Violation>
$report->fatal();      // the violations that make it non-compliant
```

Each `Violation` names the standard (`ISO_19005_2`), its clause and test, what the rule says, where the PDF breaks it,
and its `Severity`: `Fatal`, or `Warning`, raised only by the softer Factur-X rules.

## The native writer

`Document` writes PDF/A-2u itself ([Archiving](archiving.md)). `remediate` reaches the other levels, PDF/UA and
Factur-X, on any PDF, and `validate` checks what the native writer wrote:

```php
$gotenberg->validate(Files::of(InputFile::fromDocument('archive.pdf', $document)), Profile::PdfA2u);
```
