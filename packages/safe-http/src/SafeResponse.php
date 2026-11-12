<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\Url\Url;

/**
 * An answer of another site, read within the limits: the address that answered after the redirects, those redirects
 * in order, its status and headers, the media type and character set its `Content-Type` declares, and its body as far
 * as it was read. `complete` says whether the body was read to its end; it is false when the body was cut at the bytes
 * of the limits or at the stop mark, or not read at all.
 */
final readonly class SafeResponse
{
    /**
     * @param array<string, list<string>> $headers   by lowercase name
     * @param list<Url>                   $redirects the addresses redirected to, the last one being `$url`
     */
    public function __construct(
        public Url $url,
        public int $status,
        public array $headers,
        public string $body,
        public bool $complete,
        public array $redirects = [],
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * The media type of the body in lowercase, `text/html`, without its parameters; null when none is declared.
     */
    public function mediaType(): ?string
    {
        return self::mediaTypeOf($this->header('content-type'));
    }

    /**
     * The character set the `Content-Type` declares, as written.
     */
    public function charset(): ?string
    {
        return 1 === preg_match('/;\s*charset\s*=\s*["\']?([\w.:-]{1,40})/i', (string) $this->header('content-type'), $charset) ? $charset[1] : null;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public static function mediaTypeOf(?string $contentType): ?string
    {
        return 1 === preg_match('{^\s*([\w!#$&^.+-]{1,127}/[\w!#$&^.+-]{1,127})\s*(?:;|$)}', (string) $contentType, $type) ? strtolower($type[1]) : null;
    }
}
