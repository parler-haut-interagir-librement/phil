<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Journal;

/**
 * What a task asks the model for.
 */
enum Output: string
{
    /** Text, as the model writes it. */
    case Text = 'text';

    /** Data shaped by a class or a JSON schema: Symfony AI's structured output. */
    case Structured = 'structured';

    /** An image generated from the prompt. */
    case Image = 'image';

    /** Speech synthesised from the prompt. */
    case Speech = 'speech';
}
