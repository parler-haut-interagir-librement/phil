<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Outbox;

/**
 * An entity a flush inserted or updated, to index again once the outbox relays it: its class and its identifier only,
 * the document read again from the entity as it then is.
 */
final readonly class IndexEntity
{
    public const string TYPE = 'phil_search.index_entity';

    /**
     * @param class-string              $class
     * @param array<string, string|int> $identifier the entity's identifier, by field
     */
    public function __construct(
        public string $class,
        public array $identifier,
    ) {
    }
}
