<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Journal;

/**
 * Whose key paid for a task: the application's, or the person's own key, which they pay their provider for.
 */
enum KeySource: string
{
    case Application = 'application';
    case User = 'user';
}
