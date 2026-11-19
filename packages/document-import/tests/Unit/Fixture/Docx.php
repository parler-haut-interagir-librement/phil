<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

/**
 * A minimal WordprocessingML package written for a test: a body, and the parts and relationships a test adds.
 */
final class Docx
{
    public const string NAMESPACES = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        .'xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" '
        .'xmlns:v="urn:schemas-microsoft-com:vml" '
        .'xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" '
        .'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';

    private const string RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';

    /** @var array<string, string> */
    private array $entries = [];

    /** @var list<string> */
    private array $relationships = [];

    private string $core = '';

    public function __construct(private readonly string $body = '')
    {
    }

    public static function body(string $body): self
    {
        return new self($body);
    }

    /**
     * A part of the document with its relationship from the main part.
     */
    public function part(string $type, string $name, string $xml, string $id = ''): self
    {
        $this->entries['word/'.$name] = $xml;

        return $this->relationship('' === $id ? 'rId'.$type : $id, $type, $name);
    }

    public function relationship(string $id, string $type, string $target, bool $external = false): self
    {
        $this->relationships[] = \sprintf('<Relationship Id="%s" Type="%s%s" Target="%s"%s/>', $id, self::RELATIONSHIPS, $type, htmlspecialchars($target, \ENT_QUOTES), $external ? ' TargetMode="External"' : '');

        return $this;
    }

    public function file(string $name, string $bytes): self
    {
        $this->entries[$name] = $bytes;

        return $this;
    }

    public function styles(string $styles): self
    {
        return $this->part('styles', 'styles.xml', '<w:styles '.self::NAMESPACES.'>'.$styles.'</w:styles>');
    }

    public function numbering(string $numbering): self
    {
        return $this->part('numbering', 'numbering.xml', '<w:numbering '.self::NAMESPACES.'>'.$numbering.'</w:numbering>');
    }

    public function footnotes(string $notes): self
    {
        return $this->part('footnotes', 'footnotes.xml', '<w:footnotes '.self::NAMESPACES.'>'.$notes.'</w:footnotes>');
    }

    public function endnotes(string $notes): self
    {
        return $this->part('endnotes', 'endnotes.xml', '<w:endnotes '.self::NAMESPACES.'>'.$notes.'</w:endnotes>');
    }

    public function core(string $properties): self
    {
        $this->core = $properties;

        return $this;
    }

    public function bytes(): string
    {
        $package = '<Relationship Id="rId1" Type="'.self::RELATIONSHIPS.'officeDocument" Target="word/document.xml"/>';

        if ('' !== $this->core) {
            $package .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
            $this->entries['docProps/core.xml'] = '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">'.$this->core.'</cp:coreProperties>';
        }

        return Zip::of([
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            '_rels/.rels' => $this->relationships($package),
            'word/document.xml' => '<w:document '.self::NAMESPACES.'><w:body>'.$this->body.'</w:body></w:document>',
            'word/_rels/document.xml.rels' => $this->relationships(implode('', $this->relationships)),
            ...$this->entries,
        ]);
    }

    private function relationships(string $relationships): string
    {
        return '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }
}
