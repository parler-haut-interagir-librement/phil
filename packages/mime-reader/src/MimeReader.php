<?php

declare(strict_types=1);

namespace Phil\MimeReader;

use Phil\MimeReader\Exception\InvalidStream;
use Phil\MimeReader\Internal\Bytes;
use Phil\MimeReader\Internal\PartBudget;
use Phil\MimeReader\Internal\TransferDecoder;

/**
 * Reads an Internet message (RFC 5322) and its MIME structure (RFC 2045, 2046): header fields, media types and their
 * parameters, multiparts of any subtype split at their boundaries, `message/rfc822` and `message/global` read as the
 * message they hold, `multipart/digest` whose parts are messages by default. Bodies stay encoded until a part is asked
 * for them.
 *
 * It never throws on a message: what it cannot read is kept as text, and what lies beyond the limits is not read and
 * marks its part incomplete. It keeps no state between calls.
 */
final readonly class MimeReader
{
    private const array MESSAGE_TYPES = ['message/rfc822', 'message/global'];

    private const array ENCODED = ['base64', 'quoted-printable'];

    public function __construct(
        private Limits $limits = new Limits(),
    ) {
    }

    public function read(string $raw): Part
    {
        $incomplete = \strlen($raw) > $this->limits->maxBytes;
        $source = $incomplete ? substr($raw, 0, $this->limits->maxBytes) : $raw;

        return $this->part($source, 0, \strlen($source), 0, new PartBudget($this->limits->maxParts), 'text/plain', $incomplete);
    }

    /**
     * Reads a message from an open stream, from its current position, at most the octets of the limits.
     *
     * @param mixed $stream an open stream
     *
     * @throws InvalidStream when `$stream` is not an open stream
     */
    public function readStream(mixed $stream): Part
    {
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new InvalidStream('Read a message from an open stream.');
        }

        return $this->read((string) stream_get_contents($stream, $this->limits->maxBytes + 1));
    }

    private function part(string $source, int $start, int $end, int $depth, PartBudget $budget, string $defaultType, bool $incomplete): Part
    {
        $budget->take();
        [$headerEnd, $bodyStart] = $this->headerEnd($source, $start, $end);
        $headers = Headers::parse(substr($source, $start, $headerEnd - $start), $this->limits->maxHeaders);
        $contentType = HeaderValue::parse($headers->get('Content-Type') ?? '', $this->limits->maxParameters);
        $type = strtolower($contentType->value);
        $type = 1 === preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/', $type) ? $type : $defaultType;

        $parameters = $contentType->parameters;
        $encoding = strtolower($headers->get('Content-Transfer-Encoding') ?? '');
        $boundary = $parameters['boundary'] ?? '';
        $container = (str_starts_with($type, 'multipart/') && 1 === preg_match('/^[ -~]{1,200}$/', $boundary))
            || \in_array($type, self::MESSAGE_TYPES, true);
        $leaf = static fn (bool $cut, array $parts = [], ?Part $message = null): Part => Part::read($headers, $type, $parameters, $encoding, $source, $bodyStart, $end - $bodyStart, $parts, $message, $cut);

        if (!$container) {
            return $leaf($incomplete);
        }

        if ($depth + 1 >= $this->limits->maxDepth || 0 === $budget->left()) {
            return $leaf(true);
        }

        // A container should not be encoded; one that is gets decoded first, and its parts read from the decoded octets.
        [$inner, $innerStart, $innerEnd] = \in_array($encoding, self::ENCODED, true)
            ? $this->decoded($source, $bodyStart, $end, $encoding)
            : [$source, $bodyStart, $end];

        if (!str_starts_with($type, 'multipart/')) {
            return $leaf($incomplete, [], $this->part($inner, $innerStart, $innerEnd, $depth + 1, $budget, 'text/plain', false));
        }

        $children = [];
        $ranges = $this->bodies($inner, $innerStart, $innerEnd, '--'.$boundary, $budget->left() + 1);

        foreach ($ranges as [$childStart, $childEnd]) {
            if (!$budget->available()) {
                $incomplete = true;

                break;
            }

            $children[] = $this->part($inner, $childStart, $childEnd, $depth + 1, $budget, 'multipart/digest' === $type ? 'message/rfc822' : 'text/plain', false);
        }

        return $leaf($incomplete, $children);
    }

    /**
     * Where the header lines end and where the body starts: at the first empty line, or at the end of the part when it
     * has none, as a part of header lines only.
     *
     * @return array{int, int}
     */
    private function headerEnd(string $source, int $start, int $end): array
    {
        foreach (["\n", "\r\n"] as $newline) {
            if (substr($source, $start, \strlen($newline)) === $newline) {
                return [$start, min($end, $start + \strlen($newline))];
            }
        }

        $lf = Bytes::find($source, "\n\n", $start, $end);
        $crlf = Bytes::find($source, "\n\r\n", $start, $lf ?? $end);

        return match (true) {
            null !== $crlf => [$crlf + 1, $crlf + 3],
            null !== $lf => [$lf + 1, $lf + 2],
            default => [$end, $end],
        };
    }

    /**
     * @return array{string, int, int}
     */
    private function decoded(string $source, int $start, int $end, string $encoding): array
    {
        $decoded = '';
        TransferDecoder::decode($source, $start, $end - $start, $encoding, static function (string $chunk) use (&$decoded): void {
            $decoded .= $chunk;
        });

        return [$decoded, 0, \strlen($decoded)];
    }

    /**
     * The ranges between the delimiter lines of a multipart, without its preamble and epilogue, at most `$limit`. A
     * delimiter counts at the start of a line followed by nothing but spaces; the line break before it belongs to it. A
     * multipart whose closing line is missing ends with its text.
     *
     * @param non-empty-string $delimiter
     *
     * @return list<array{int, int}>
     */
    private function bodies(string $source, int $start, int $end, string $delimiter, int $limit): array
    {
        $ranges = [];
        $current = null;
        $at = $start;

        while (\count($ranges) < $limit && null !== ($found = Bytes::find($source, $delimiter, $at, $end))) {
            $after = $found + \strlen($delimiter);
            $lineEnd = Bytes::find($source, "\n", $after, $end) ?? $end;
            $rest = rtrim(substr($source, $after, $lineEnd - $after), " \t\r");
            $closing = '--' === $rest;
            $at = min($lineEnd + 1, $end);

            if (($found > $start && "\n" !== $source[$found - 1]) || ('' !== $rest && !$closing)) {
                $at = $found + 1;

                continue;
            }

            if (null !== $current) {
                $partEnd = $found - 1 - ("\r" === $source[$found - 2] ? 1 : 0);
                $ranges[] = [$current, max($current, $partEnd)];
            }

            if ($closing) {
                return $ranges;
            }

            $current = $at;
        }

        if (null !== $current && \count($ranges) < $limit) {
            $ranges[] = [$current, $end];
        }

        return $ranges;
    }
}
