<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

/**
 * A call of a tool the model proposed: nothing ran. The application shows it, and runs it only once a person agrees.
 */
final readonly class ProposedToolCall
{
    /**
     * @param array<string, mixed> $arguments as the model wrote them, unchecked against the tool's schema
     */
    public function __construct(
        /** The provider's identifier of the call, to answer it in a later turn. */
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {
    }
}
