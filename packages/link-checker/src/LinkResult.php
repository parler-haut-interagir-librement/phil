<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * What the check of one address found: its status, the HTTP code of the last answer, the address that answered after
 * the redirects, and a short reason in English when the code alone does not say it.
 */
final readonly class LinkResult
{
    public function __construct(
        public LinkStatus $status,
        public ?int $code = null,
        public ?Url $target = null,
        public ?string $reason = null,
    ) {
    }

    /**
     * The result as an array of scalars, as a cache keeps it.
     *
     * @return array{status: string, code: int|null, target: string|null, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'code' => $this->code,
            'target' => $this->target?->toString(),
            'reason' => $this->reason,
        ];
    }

    /**
     * The result an array of `toArray()` holds; null when the array has another shape, as an entry written by another
     * version could.
     */
    public static function fromArray(mixed $data): ?self
    {
        $data = \is_array($data) ? $data : [];
        $status = \is_string($data['status'] ?? null) ? LinkStatus::tryFrom($data['status']) : null;
        $code = $data['code'] ?? null;
        $target = $data['target'] ?? null;
        $reason = $data['reason'] ?? null;

        if (!$status instanceof LinkStatus || (null !== $code && !\is_int($code)) || (null !== $target && !\is_string($target)) || (null !== $reason && !\is_string($reason))) {
            return null;
        }

        try {
            return new self($status, $code, null === $target ? null : Url::fromString($target), $reason);
        } catch (UrlException) {
            return null;
        }
    }
}
