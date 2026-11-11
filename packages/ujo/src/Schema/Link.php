<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Html\SafeUrl;

/**
 * An address of one of the allowed schemes — `http` and `https` by default — or relative when allowed.
 */
final readonly class Link implements Sized
{
    /**
     * @param list<string> $schemes in lower case
     */
    public function __construct(
        private array $schemes = SafeUrl::MEDIA,
        private bool $relative = true,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): string
    {
        if (!\is_string($value) || !SafeUrl::isSafe($value, $this->schemes, $this->relative)) {
            throw InvalidDocument::at($path, \sprintf('an address in %s%s is expected', implode(', ', $this->schemes), $this->relative ? ', or relative,' : ''));
        }

        return $value;
    }

    #[Override]
    public function size(mixed $value): int
    {
        return \is_string($value) ? mb_strlen($value) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'characters';
    }
}
