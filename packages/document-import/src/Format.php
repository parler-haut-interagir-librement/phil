<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Phil\DocumentImport\Exception\UnsupportedFormat;

/**
 * The formats the importer reads: the word processing documents of Office Open XML — transitional or strict — and
 * OpenDocument, packaged or flat, Rich Text Format, PDF, Markdown and HTML. The binary Word documents of Word 97 to
 * 2003 are recognised and refused.
 */
enum Format: string
{
    /** Office Open XML word processing: `.docx`, its templates and its macro-enabled forms. */
    case Docx = 'docx';

    /** OpenDocument text: `.odt` and its templates. */
    case Odt = 'odt';

    /** Flat OpenDocument text: one XML file, `.fodt`. */
    case Fodt = 'fodt';

    /** Rich Text Format: `.rtf`. */
    case Rtf = 'rtf';

    /** Portable Document Format: `.pdf`, its text and simple structure. */
    case Pdf = 'pdf';

    case Markdown = 'markdown';

    case Html = 'html';

    private const string ZIP = "PK\x03\x04";

    private const string ODF_TEXT = 'mimetypeapplication/vnd.oasis.opendocument.text';

    /** The signature of a compound file: a Word 97–2003 document, or an Office document protected by a password. */
    private const string COMPOUND = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const string LEGACY = 'The file is a Word 97–2003 binary document, or an Office document protected by a password, which this library does not read: save it as DOCX, ODT or RTF, without password.';

    /**
     * The format a file name announces by its extension, `null` when it announces none of these.
     *
     * @throws UnsupportedFormat when it announces a Word 97–2003 binary document
     */
    public static function fromFilename(string $filename): ?self
    {
        return match (strtolower(pathinfo($filename, \PATHINFO_EXTENSION))) {
            'docx', 'docm', 'dotx', 'dotm' => self::Docx,
            'odt', 'ott' => self::Odt,
            'fodt' => self::Fodt,
            'rtf' => self::Rtf,
            'pdf' => self::Pdf,
            'doc', 'dot' => throw UnsupportedFormat::because(self::LEGACY),
            'md', 'markdown', 'mdown', 'mkd' => self::Markdown,
            'html', 'htm', 'xhtml' => self::Html,
            default => null,
        };
    }

    /**
     * The format of a file: the one its name announces, otherwise the one its bytes show — an OpenDocument text
     * archive by its `mimetype` entry, another ZIP archive as Office Open XML, `%PDF-` as PDF, `{\rtf` as RTF, an
     * XML `office:document` of a text as flat OpenDocument, text that starts with `<` as HTML. Markdown is only known
     * by its name. A compound file — a Word 97–2003 document, or an Office document protected by a password — is
     * refused whatever its name.
     *
     * @throws UnsupportedFormat
     */
    public static function detect(string $contents, ?string $filename = null): self
    {
        self::refuseLegacy($contents);
        $named = null === $filename ? null : self::fromFilename($filename);

        if ($named instanceof Format) {
            return $named;
        }

        $start = ltrim(substr($contents, 0, 4096), "\u{FEFF} \t\r\n");

        return match (true) {
            str_starts_with($contents, self::ZIP) => str_starts_with(substr($contents, 30), self::ODF_TEXT) ? self::Odt : self::Docx,
            str_starts_with($contents, '%PDF-') => self::Pdf,
            str_starts_with($start, '{\rtf') => self::Rtf,
            1 === preg_match('/<office:document\b[^>]*office:mimetype="application\/vnd\.oasis\.opendocument\.text"/', $start) => self::Fodt,
            str_starts_with($start, '<') => self::Html,
            default => throw UnsupportedFormat::because(\sprintf('The format of %s is not recognised: give it, or a file name ending with .docx, .odt, .fodt, .rtf, .pdf, .md or .html.', null === $filename ? 'the file' : '"'.$filename.'"')),
        };
    }

    /**
     * @throws UnsupportedFormat when the bytes are those of a compound file
     */
    public static function refuseLegacy(string $contents): void
    {
        if (str_starts_with($contents, self::COMPOUND)) {
            throw UnsupportedFormat::because(self::LEGACY);
        }
    }
}
