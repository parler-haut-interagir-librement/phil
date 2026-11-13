# Reference

| Type | Kind | Role | State |
|---|---|---|---|
| `Pdf\Document` | `final class` | The native writer: pages, text, shapes, images, form fields, rendering | Implemented and tested |
| `Pdf\PageSize` | `final readonly class` | A page's width and height in points | Implemented and tested |
| `Pdf\Color` | `final readonly class` | A grey level or an RGB colour | Implemented and tested |
| `Pdf\Metadata` | `final readonly class` | The information dictionary, and the XMP packet of a document that conforms | Implemented and tested |
| `Pdf\Conformance` | `enum` | A standard a document conforms to: PDF/A-2u | Implemented and tested |
| `Pdf\Form\TextField` | `final readonly class` | A text box a reader fills, on one line or several | Implemented and tested |
| `Pdf\Form\CheckBox` | `final readonly class` | A square a reader checks, with its export value | Implemented and tested |
| `Pdf\Form\RadioGroup`, `RadioButton` | `final readonly class` | Circles of which a reader chooses one, and each choice | Implemented and tested |
| `Pdf\Form\ChoiceField` | `final readonly class` | A combo box, editable or not, or a list box | Implemented and tested |
| `Pdf\Form\Fields`, `Widget`, `FormWriter`, `Check` | `final class`, `@internal` | Widgets and their appearance streams, the interactive form or its flattening, the checks of a field | Implemented and tested |
| `Template\LabelSheet` | `final readonly class` | A grid of labels or cards on a page, and the common stock sizes | Implemented and tested |
| `Template\TemplateFonts` | `final readonly class` | The regular and bold fonts a template writes in | Implemented and tested |
| `Template\NameBadges`, `Badge` | `final readonly class` | Name badges of an event, and one badge | Implemented and tested |
| `Template\AddressLabels`, `AddressLabel` | `final readonly class` | Address labels, and one address | Implemented and tested |
| `Template\ProductLabels`, `ProductLabel` | `final readonly class` | Product labels, and one product | Implemented and tested |
| `Template\ShelfLabels`, `ShelfLabel` | `final readonly class` | Shelf labels, and one shelf label | Implemented and tested |
| `Template\GiftCards`, `GiftCard` | `final readonly class` | Gift cards, and one card | Implemented and tested |
| `Template\DownloadCards`, `DownloadCard` | `final readonly class` | Download cards, one per code | Implemented and tested |
| `Template\Calendar`, `CalendarMonth` | `final readonly class` | A printed calendar, a month per page, and one month | Implemented and tested |
| `Template\LargePrint`, `LargePrintBlock` | `final readonly class` | A large-print edition, and its headings and paragraphs | Implemented and tested |
| `Template\Layout` | `final class`, `@internal` | Labels laid out on sheets, text fitted to a width | Implemented and tested |
| `Css\PageRule` | `final readonly class` | The `@page` rule of a paged-media engine: size, margins, bleed, printer's marks | Implemented and tested |
| `Css\PrinterMarks` | `enum` | The marks drawn in the bleed: none, crop, cross, or both | Implemented and tested |
| `Pdf\SrgbProfile` | `final class`, `@internal` | The ICC sRGB profile of the output intent | Implemented and tested |
| `Pdf\Objects` | `final class`, `@internal` | Numbered objects and the cross-reference table | Implemented and tested |
| `Pdf\Number` | `final class`, `@internal` | Numbers as PDF writes them | Implemented and tested |
| `Pdf\Bytes` | `final class`, `@internal` | Hexadecimal and big-endian helpers | Implemented and tested |
| `Font\Font` | `interface` | What text is written and measured in | Implemented and tested |
| `Font\StandardFont` | `enum` | Helvetica and Helvetica Bold in Windows-1252 | Implemented and tested |
| `Font\TrueTypeFont` | `final class` | A TrueType font read, measured, reduced for embedding, or embedded whole for a form | Implemented and tested |
| `Image\Image` | `final readonly class` | A JPEG or PNG image ready to place | Implemented and tested |
| `Exception\PagedMediaException` | `interface` | Implemented by every exception of the library | Implemented and tested |
| `Exception\InvalidDocument` | `final class` | A document built in a way that cannot be written | Implemented and tested |
| `Exception\InvalidFont` | `final class` | A font that cannot be read or embedded | Implemented and tested |
| `Exception\InvalidImage` | `final class` | An image that cannot be read | Implemented and tested |
| `Gotenberg\Gotenberg` | `final readonly class` | The client: stages in a list or alone, the LibreOffice conversion, the read routes, the version | Implemented and tested |
| `Gotenberg\BasicAuth` | `final readonly class` | The credentials of a Gotenberg with basic authentication | Implemented and tested |
| `Gotenberg\InputFile` | `final readonly class` | A file sent, read from a path in chunks, held in memory, or rendered from a `Document`; `path()` gives the path, null in memory | Implemented and tested |
| `Gotenberg\Files` | `final readonly class` | The files, archives and downloads a request works on | Implemented and tested |
| `Gotenberg\Download` | `final readonly class` | A URL Gotenberg downloads first, with its headers | Implemented and tested |
| `Gotenberg\Pages` | `final readonly class` | A page selection | Implemented and tested |
| `Gotenberg\Pipeline` | `final readonly class` | A list of stages, checked against Gotenberg's order rules | Implemented and tested |
| `Gotenberg\Profile` | `enum` | A PDF/A, PDF/UA or Factur-X profile, and the pairs they make | Implemented and tested |
| `Gotenberg\RequestOptions` | `final readonly class` | The result's name, the trace and the webhook of a request | Implemented and tested |
| `Gotenberg\Webhook`, `Gotenberg\WebhookMethod` | `final readonly class`, `enum` | Where Gotenberg sends a result instead of answering with it | Implemented and tested |
| `Gotenberg\WebhookEvent` | `final readonly class` | The event posted to a webhook's events URL, read | Implemented and tested |
| `Gotenberg\Result` | `final readonly class` | A file or a ZIP that Gotenberg answered, with its name and remediation counts | Implemented and tested |
| `Gotenberg\Stage\Stage` | `interface` | An operation on a PDF, its options and its files | Implemented and tested |
| `Gotenberg\Stage\Operation` | `enum` | The operations, their routes and the rules of their order | Implemented and tested |
| `Gotenberg\Stage\Merge`, `Split`, `Select`, `Delete`, `Insert`, `Rotate` | `final readonly class` | The stages that assemble pages | Implemented and tested |
| `Gotenberg\Stage\StampText`, `StampImage`, `StampPdf`, `StampFont` | `final readonly class`, `enum` | The stamps, and the 14 standard fonts of a text stamp | Implemented and tested |
| `Gotenberg\Stage\SetMetadata`, `SetBookmarks`, `Bookmark`, `SetForm`, `FlattenForm` | `final readonly class` | The stages that write the information, the outline and the form | Implemented and tested |
| `Gotenberg\Stage\FlattenTransparency`, `ConvertColors`, `ColorTarget`, `Redact`, `Region`, `Autotag` | `final readonly class`, `enum` | The stages that change the content: transparency, colours, redaction, tags | Implemented and tested |
| `Gotenberg\Stage\Attach`, `Relationship`, `Detach` | `final readonly class`, `enum` | The embedded files | Implemented and tested |
| `Gotenberg\Stage\Optimize`, `Encrypt`, `EncryptionAlgorithm`, `Permissions`, `Decrypt` | `final readonly class`, `enum` | Size and protection | Implemented and tested |
| `Gotenberg\Stage\Remediate`, `FacturXLevel` | `final readonly class`, `enum` | The repair to PDF/A, PDF/UA or Factur-X | Implemented and tested |
| `Gotenberg\Stage\Sign`, `Timestamp` | `final readonly class` | The signature and the document timestamp | Implemented and tested |
| `Gotenberg\Stage\Render`, `ImageFormat`, `ExtractText`, `ExtractAttachments` | `final readonly class`, `enum` | The terminal stages: images, text, embedded files | Implemented and tested |
| `Gotenberg\Signing\Certificate` | `final readonly class` | A PKCS#12 or PEM signing certificate | Implemented and tested |
| `Gotenberg\Signing\CertificateSource` | `interface` | Where the signing certificate comes from | Implemented and tested |
| `Gotenberg\Signing\FileCertificateSource`, `InlineCertificateSource`, `VaultCertificateSource` | `final readonly class` | A certificate on disk, held as text, or in HashiCorp Vault or OpenBao | Implemented and tested |
| `Gotenberg\Signing\TimestampAuthority`, `TimestampAuthorityUrl` | `interface`, `final readonly class` | An RFC 3161 timestamp authority, known by its URL | Implemented and tested |
| `Gotenberg\LibreOffice\LibreOfficeOptions` | `final readonly class` | The options of a LibreOffice conversion | Implemented and tested |
| `Gotenberg\LibreOffice\PaperSize`, `InitialView`, `Magnification`, `PageLayout` | `enum` | The values of the conversion's options | Implemented and tested |
| `Gotenberg\Read\PdfInfo`, `DocumentInformation`, `Attachment`, `FormField`, `FormFieldType` | `final readonly class`, `enum` | What the read routes answer | Implemented and tested |
| `Gotenberg\Read\ValidationReport`, `Violation`, `Severity` | `final readonly class`, `enum` | What a validation found | Implemented and tested |
| `Gotenberg\Read\JsonValue` | `final readonly class`, `@internal` | JSON read with its expected types | Implemented and tested |
| `Gotenberg\Internal\Multipart`, `Check`, `Url` | `final class`, `@internal` | The multipart body, the checks of the stages, the service and public addresses and the headers | Implemented and tested |
| `Gotenberg\Exception\GotenbergException` | `interface` | Implemented by every exception of the client | Implemented and tested |
| `Gotenberg\Exception\InvalidStage`, `InvalidPipeline`, `InvalidRequest` | `final class` | A request refused before it is sent | Implemented and tested |
| `Gotenberg\Exception\GotenbergFailure` | `abstract class` | An answer that is not a success, with its status and trace | Implemented and tested |
| `Gotenberg\Exception\RequestRefused`, `AccessDenied`, `RouteUnavailable`, `ServiceUnavailable`, `ConversionFailed`, `UnexpectedResponse` | `final class` | The failures by status | Implemented and tested |
| `Gotenberg\Exception\CertificateUnavailable` | `final class` | A certificate its source could not give | Implemented and tested |
