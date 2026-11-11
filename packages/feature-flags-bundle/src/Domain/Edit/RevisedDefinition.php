<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Edit;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;

/**
 * A stored definition and its revision, which each change increments: an edit is applied only against the
 * revision it was made from.
 */
final readonly class RevisedDefinition
{
    public function __construct(
        public FlagDefinition $definition,
        public int $revision,
    ) {
        if ($revision < 1) {
            throw InvalidValue::because(\sprintf('A revision is at least 1; got %d.', $revision));
        }
    }
}
