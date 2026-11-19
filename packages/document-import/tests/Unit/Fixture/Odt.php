<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

/**
 * A minimal OpenDocument text written for a test: the text of its body, its automatic styles, and the common styles,
 * metadata and files a test adds.
 */
final class Odt
{
    public const string NAMESPACES = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
        .'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
        .'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
        .'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" '
        .'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" '
        .'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" '
        .'xmlns:xlink="http://www.w3.org/1999/xlink" '
        .'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        .'xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" '
        .'xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"';

    /** @var array<string, string> */
    private array $entries = [];

    private string $automatic = '';

    public function __construct(private readonly string $text = '')
    {
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    public function automatic(string $styles): self
    {
        $this->automatic = $styles;

        return $this;
    }

    /**
     * The `styles.xml` entry with its common styles, and what else it holds — master pages with headers and footers.
     */
    public function styles(string $styles, string $rest = ''): self
    {
        $this->entries['styles.xml'] = '<office:document-styles '.self::NAMESPACES.'><office:styles>'.$styles.'</office:styles>'.$rest.'</office:document-styles>';

        return $this;
    }

    public function meta(string $meta): self
    {
        $this->entries['meta.xml'] = '<office:document-meta '.self::NAMESPACES.'><office:meta>'.$meta.'</office:meta></office:document-meta>';

        return $this;
    }

    public function file(string $name, string $bytes): self
    {
        $this->entries[$name] = $bytes;

        return $this;
    }

    public function bytes(): string
    {
        return Zip::of([
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => '<office:document-content '.self::NAMESPACES.'><office:automatic-styles>'.$this->automatic.'</office:automatic-styles><office:body><office:text>'.$this->text.'</office:text></office:body></office:document-content>',
            ...$this->entries,
        ]);
    }
}
