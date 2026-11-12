<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Meilisearch;

use Phil\SearchBundle\Domain\Exception\SearchException;
use RuntimeException;

/**
 * Meilisearch refused a request.
 */
final class SearchFailed extends RuntimeException implements SearchException
{
    public static function answered(string $what, int $status): self
    {
        return new self(\sprintf('Meilisearch answered %s with %d.', $what, $status));
    }
}
