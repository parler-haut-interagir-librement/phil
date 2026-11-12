<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\InvalidLimits;

/**
 * What of an answer's body is read: the media types asked for in `Accept`, those whose body is read — any when the list
 * is empty, none of the others —, and a mark after which the transfer stops, `</head` for the tags of a page. The body
 * is never read past the bytes of the limits.
 */
final readonly class ReadRule
{
    /**
     * @param list<string> $types media types in lowercase, such as `text/html`
     */
    public function __construct(
        public string $accept = '*/*',
        public array $types = [],
        public ?string $stopAfter = null,
    ) {
        if (!Limits::isHeaderValue($accept)) {
            throw InvalidLimits::header('Accept');
        }
    }

    /**
     * The head of an HTML page: HTML or XHTML only, up to the end of its head.
     */
    public static function htmlHead(): self
    {
        return new self('text/html, application/xhtml+xml;q=0.9', ['text/html', 'application/xhtml+xml'], '</head');
    }

    /**
     * Whether the body of an answer of this media type is read.
     */
    public function reads(?string $type): bool
    {
        return [] === $this->types || \in_array($type, $this->types, true);
    }
}
