<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Rdf;

/**
 * The terms and quads of canonical N-Quads: an IRI between angle brackets, a blank node as `_:label`, a literal
 * between quotes with the escapes of RDF 1.2 and its datatype unless it is `xsd:string`.
 *
 * @internal
 */
final class NQuads
{
    public const string XSD = 'http://www.w3.org/2001/XMLSchema#';

    public const string RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const array ESCAPES = ["\x08" => '\b', "\t" => '\t', "\n" => '\n', "\x0C" => '\f', "\r" => '\r', '"' => '\"', '\\' => '\\\\'];

    private function __construct()
    {
    }

    public static function iri(string $iri): string
    {
        return '<'.$iri.'>';
    }

    public static function literal(string $lexical, string $datatype = self::XSD.'string', ?string $language = null): string
    {
        $escaped = '"'.preg_replace_callback('/[\x00-\x1F"\\\\\x7F]/', static fn (array $match): string => self::ESCAPES[$match[0]] ?? \sprintf('\u%04X', \ord($match[0])), $lexical).'"';

        if (null !== $language) {
            return $escaped.'@'.$language;
        }

        return self::XSD.'string' === $datatype ? $escaped : $escaped.'^^'.self::iri($datatype);
    }

    /**
     * A quad as a line of N-Quads, ending with its line feed.
     *
     * @param array{string, string, string, string} $quad subject, predicate, object, and graph or ''
     */
    public static function line(array $quad): string
    {
        return $quad[0].' '.$quad[1].' '.$quad[2].('' === $quad[3] ? '' : ' '.$quad[3])." .\n";
    }
}
