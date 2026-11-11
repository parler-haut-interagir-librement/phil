<?php

declare(strict_types=1);

namespace Phil\BounceParser\Mime;

use Phil\BounceParser\Limits;
use SodiumException;

/**
 * Reads an Internet message (RFC 5322) and its MIME structure (RFC 2045, 2046): header fields, media types and their
 * parameters, base64 and quoted-printable bodies, multiparts split at their boundaries, `message/rfc822` and
 * `message/global` read as the message they hold, `text/rfc822-headers` as header fields. It never throws: what it
 * cannot read is kept as text, and what lies beyond the limits is not read.
 */
final readonly class MimeParser
{
    private const array MESSAGE_TYPES = ['message/rfc822', 'message/global'];

    public function __construct(
        private Limits $limits = new Limits(),
    ) {
    }

    public function parse(string $raw): Part
    {
        $budget = new PartBudget($this->limits->maxParts);

        return $this->part(substr($raw, 0, $this->limits->maxBytes), 0, $budget, 'text/plain');
    }

    private function part(string $raw, int $depth, PartBudget $budget, string $defaultType): Part
    {
        $budget->take();
        [$head, $body] = $this->split($raw);
        $headers = Headers::parse($head, $this->limits->maxHeaders);
        [$type, $parameters] = $this->contentType($headers->get('Content-Type'), $defaultType);
        $body = $this->decode($body, strtolower($headers->get('Content-Transfer-Encoding') ?? ''));
        $nested = $depth + 1 < $this->limits->maxDepth;

        if ($nested && str_starts_with($type, 'multipart/') && '' !== ($parameters['boundary'] ?? '')) {
            $children = [];
            $childType = 'multipart/digest' === $type ? 'message/rfc822' : 'text/plain';

            foreach ($this->bodies($body, $parameters['boundary']) as $child) {
                if ($budget->available()) {
                    $children[] = $this->part($child, $depth + 1, $budget, $childType);
                }
            }

            return new Part($headers, $type, $parameters, $body, $children);
        }

        if ($nested && \in_array($type, self::MESSAGE_TYPES, true) && $budget->available()) {
            return new Part($headers, $type, $parameters, $body, [], $this->part($body, $depth + 1, $budget, 'text/plain'));
        }

        return new Part($headers, $type, $parameters, $body);
    }

    /**
     * @return array{string, string} the header lines and the body
     */
    private function split(string $raw): array
    {
        // A part that starts with an empty line has no header fields.
        if (1 !== preg_match('/(?:^|\r?\n)\r?\n/', $raw, $match, \PREG_OFFSET_CAPTURE)) {
            return [$raw, ''];
        }

        $offset = $match[0][1];

        return [substr($raw, 0, $offset), substr($raw, $offset + \strlen($match[0][0]))];
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function contentType(?string $value, string $default): array
    {
        $segments = $this->segments($value ?? '');
        $type = strtolower(trim(array_shift($segments)));
        $parameters = [];

        foreach ($segments as $segment) {
            $equals = strpos($segment, '=');

            if (false !== $equals) {
                $parameters[strtolower(trim(substr($segment, 0, $equals)))] = $this->unquote(trim(substr($segment, $equals + 1)));
            }
        }

        if (1 !== preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/', $type)) {
            $type = $default;
        }

        return [$type, $parameters];
    }

    /**
     * The value split at the semicolons outside quotes.
     *
     * @return non-empty-list<string>
     */
    private function segments(string $value): array
    {
        $segments = [''];
        $quoted = false;
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];

            if ('\\' === $char && $quoted) {
                $segments[\count($segments) - 1] .= $char.substr($value, ++$i, 1);
            } elseif ('"' === $char) {
                $quoted = !$quoted;
                $segments[\count($segments) - 1] .= $char;
            } elseif (';' === $char && !$quoted) {
                $segments[] = '';
            } else {
                $segments[\count($segments) - 1] .= $char;
            }
        }

        return $segments;
    }

    private function unquote(string $value): string
    {
        if (\strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return (string) preg_replace('/\\\(.)/s', '$1', substr($value, 1, -1));
        }

        return $value;
    }

    private function decode(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => $this->base64($body),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Base64 without what is not of its alphabet, line breaks first; an impossible length gives nothing.
     */
    private function base64(string $body): string
    {
        try {
            return sodium_base642bin((string) preg_replace('/[^A-Za-z0-9+\/]/', '', $body), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
        } catch (SodiumException) {
            return '';
        }
    }

    /**
     * The bodies between the boundary lines of a multipart, without its preamble and epilogue. A multipart whose
     * closing line is missing ends with the text.
     *
     * @return list<string>
     */
    private function bodies(string $body, string $boundary): array
    {
        $lines = preg_split('/\r?\n/', $body);
        $delimiter = '--'.$boundary;
        $bodies = [];
        $current = null;

        foreach (false === $lines ? [] : $lines as $line) {
            $trimmed = rtrim($line, " \t");

            if ($trimmed === $delimiter.'--') {
                break;
            }

            if ($trimmed === $delimiter) {
                if (null !== $current) {
                    $bodies[] = implode("\n", $current);
                }

                $current = [];

                continue;
            }

            if (null !== $current) {
                $current[] = $line;
            }
        }

        if (null !== $current) {
            $bodies[] = implode("\n", $current);
        }

        return $bodies;
    }
}
