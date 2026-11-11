<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Toggle;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * An operator asking to set a flag active or inactive, with the reason. `$actor` is the application's opaque
 * identifier of the operator; an empty or blank actor, blank in the Unicode sense of {@see ToggleReason::BLANK},
 * is refused.
 */
final readonly class ToggleRequest
{
    public function __construct(
        public FlagName $flag,
        public bool $active,
        public string $actor,
        public ToggleReason $reason,
    ) {
        if (!mb_check_encoding($actor, 'UTF-8')) {
            throw InvalidValue::because('The actor of a toggle must be valid UTF-8.');
        }

        if (1 === preg_match('/^'.ToggleReason::BLANK.'*$/uD', $actor)) {
            throw InvalidValue::because('A toggle needs an actor; an anonymous toggle cannot be audited.');
        }
    }
}
