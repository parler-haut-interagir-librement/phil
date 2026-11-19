<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\ValueObject\Tokens;

/**
 * What the model answered — text, structured data, tool calls it proposes, or a generated image or speech — and the
 * tokens it counted.
 */
final readonly class Completion
{
    /**
     * @param object|array<array-key, mixed>|null $data      the structured output, for a task that asks for one
     * @param list<ProposedToolCall>              $toolCalls the tools the model proposes to call
     */
    public function __construct(
        public string $text,
        public Tokens $tokens,
        public object|array|null $data = null,
        public array $toolCalls = [],
        public ?Media $media = null,
    ) {
    }
}
