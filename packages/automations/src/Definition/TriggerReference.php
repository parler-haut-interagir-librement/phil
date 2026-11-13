<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * The trigger a definition starts on: the type a trigger registers, with the options that narrow the events it
 * matches.
 */
final readonly class TriggerReference
{
    private function __construct(
        public string $type,
        public Options $options,
    ) {
    }

    /**
     * @throws InvalidDefinition when the type is not a component type
     */
    public static function of(string $type, ?Options $options = null): self
    {
        return new self(ComponentType::check($type), $options ?? Options::none());
    }
}
