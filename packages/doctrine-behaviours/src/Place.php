<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\DoctrineBehaviours\Contract\Positionable;

/**
 * Where a stored `Positionable` record is: its table and column, its group, and the condition that finds its row.
 *
 * @internal
 */
final readonly class Place
{
    /**
     * @param ClassMetadata<object>           $metadata
     * @param list<mixed>                     $identifier
     * @param list<ParameterType|string|Type> $identifierTypes
     */
    public function __construct(
        public Positionable $record,
        public ClassMetadata $metadata,
        public string $table,
        public string $column,
        public Scope $scope,
        public string $identity,
        public array $identifier,
        public array $identifierTypes,
    ) {
    }
}
