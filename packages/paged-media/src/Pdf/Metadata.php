<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

use DateTimeImmutable;

/**
 * What a document says of itself in its information dictionary. Without a creation date the same document is the same
 * bytes, which is why none is written unless one is given.
 */
final readonly class Metadata
{
    public const string PRODUCER = 'phil/paged-media';

    public function __construct(
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $creator = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /**
     * @return array<string, string> the entries of the information dictionary, keyed by name, as PDF strings
     */
    public function entries(): array
    {
        $entries = [];

        foreach (['Title' => $this->title, 'Author' => $this->author, 'Subject' => $this->subject, 'Keywords' => $this->keywords, 'Creator' => $this->creator, 'Producer' => self::PRODUCER] as $name => $value) {
            if ($this->filled($value)) {
                $entries[$name] = self::text($value);
            }
        }

        if ($this->createdAt instanceof DateTimeImmutable) {
            $entries['CreationDate'] = '('.$this->date($this->createdAt).')';
        }

        return $entries;
    }

    /**
     * The XMP packet of a document that conforms to a standard: the same title, author, subject, keywords, creator,
     * producer and creation date as the information dictionary, which PDF/A requires to agree, and the conformance.
     */
    public function xmp(Conformance $conformance): string
    {
        $alternative = static fn (string $value): string => '<rdf:Alt><rdf:li xml:lang="x-default">'.self::xml($value).'</rdf:li></rdf:Alt>';
        [$part, $level] = match ($conformance) {
            Conformance::PdfA2u => ['2', 'U'],
        };
        $properties = [
            'pdfaid:part' => $part,
            'pdfaid:conformance' => $level,
            'dc:title' => $this->filled($this->title) ? $alternative($this->title) : null,
            'dc:creator' => $this->filled($this->author) ? '<rdf:Seq><rdf:li>'.self::xml($this->author).'</rdf:li></rdf:Seq>' : null,
            'dc:description' => $this->filled($this->subject) ? $alternative($this->subject) : null,
            'pdf:Keywords' => $this->filled($this->keywords) ? self::xml($this->keywords) : null,
            'xmp:CreatorTool' => $this->filled($this->creator) ? self::xml($this->creator) : null,
            'xmp:CreateDate' => $this->createdAt?->format('Y-m-d\\TH:i:sP'),
            'pdf:Producer' => self::PRODUCER,
        ];
        $body = '';

        foreach ($properties as $name => $value) {
            if (null !== $value) {
                $body .= \sprintf("   <%1\$s>%2\$s</%1\$s>\n", $name, $value);
            }
        }

        return "<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n"
            ."<x:xmpmeta xmlns:x=\"adobe:ns:meta/\">\n"
            ." <rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\">\n"
            ."  <rdf:Description rdf:about=\"\" xmlns:pdfaid=\"http://www.aiim.org/pdfa/ns/id/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:pdf=\"http://ns.adobe.com/pdf/1.3/\" xmlns:xmp=\"http://ns.adobe.com/xap/1.0/\">\n"
            .$body
            ."  </rdf:Description>\n"
            ." </rdf:RDF>\n"
            ."</x:xmpmeta>\n"
            .'<?xpacket end="w"?>';
    }

    /**
     * A text string: in parentheses when it is printable ASCII, in UTF-16BE with its byte order mark otherwise.
     */
    public static function text(string $value): string
    {
        if (1 === preg_match('/\A[\x20-\x7E]*\z/', $value)) {
            return '('.strtr($value, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']).')';
        }

        return '<FEFF'.Bytes::hex(mb_convert_encoding($value, 'UTF-16BE', 'UTF-8')).'>';
    }

    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function filled(?string $value): bool
    {
        return null !== $value && '' !== $value;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    private function date(DateTimeImmutable $date): string
    {
        $offset = $date->format('P');

        return 'D:'.$date->format('YmdHis').('+00:00' === $offset ? 'Z' : str_replace(':', "'", $offset)."'");
    }
}
