<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

use Phil\PwaBundle\Domain\Manifest\CacheStrategy;

/**
 * How the service worker answers the requests whose path starts with `$prefix`.
 */
final readonly class CacheRule
{
    /** @var non-empty-string */
    public string $prefix;

    public function __construct(
        string $prefix,
        public CacheStrategy $strategy,
    ) {
        $this->prefix = Rule::localPath($prefix, 'prefix of a cache rule');
    }
}
