<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Edit;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;

/**
 * An operator asking to create a flag, or to change the rules, the experiment or the metadata of one, with the
 * reason. A creation names no revision; a change names the revision it was made against, so that two operators
 * editing the same flag cannot overwrite each other.
 *
 * The active state is not edited here: it is toggled, on its own audit line.
 */
final readonly class EditRequest
{
    public function __construct(
        public FlagDefinition $definition,
        public string $actor,
        public ToggleReason $reason,
        public ?int $revision = null,
    ) {
        if (!mb_check_encoding($actor, 'UTF-8')) {
            throw InvalidValue::because('The actor of an edit must be valid UTF-8.');
        }

        if (1 === preg_match('/^'.ToggleReason::BLANK.'*$/uD', $actor)) {
            throw InvalidValue::because('An edit needs an actor; an anonymous edit cannot be audited.');
        }

        if (null !== $revision && $revision < 1) {
            throw InvalidValue::because(\sprintf('A revision is at least 1; got %d.', $revision));
        }
    }

    public function flag(): FlagName
    {
        return $this->definition->name;
    }

    public function isCreation(): bool
    {
        return null === $this->revision;
    }
}
