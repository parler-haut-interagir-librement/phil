<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Journal;

/**
 * How the call to the model ended.
 */
enum Outcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
