<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

/**
 * What a source answered last to make the next read conditional: its `ETag` and its `Last-Modified`, sent back as
 * `If-None-Match` and `If-Modified-Since`. A value that is empty, longer than `MAX_BYTES` or holds a control character
 * is not kept.
 */
final readonly class FetchValidators
{
    public const int MAX_BYTES = 1024;

    public ?string $etag;

    public ?string $lastModified;

    public function __construct(?string $etag = null, ?string $lastModified = null)
    {
        $this->etag = $this->kept($etag);
        $this->lastModified = $this->kept($lastModified);
    }

    public function isEmpty(): bool
    {
        return null === $this->etag && null === $this->lastModified;
    }

    /**
     * The headers of a conditional request.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return array_filter(['If-None-Match' => $this->etag, 'If-Modified-Since' => $this->lastModified], static fn (?string $value): bool => null !== $value);
    }

    private function kept(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value || \strlen($value) > self::MAX_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $value) ? null : $value;
    }
}
