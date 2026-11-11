<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit\Fixtures;

use Override;
use Phil\InstantMessenger\MessengerType;

/**
 * An application's own service, for the tests, whose `display()` decorates the handle, `ada (forum)`: a text it does
 * not read back.
 */
enum LabelledMessenger: string implements MessengerType
{
    case Board = 'board';

    #[Override]
    public function normalize(string $input): ?string
    {
        return 1 === preg_match('/^[a-z]{3,20}$/D', $input) ? $input : null;
    }

    #[Override]
    public function display(string $handle): string
    {
        return $handle.' (forum)';
    }

    #[Override]
    public function link(string $handle): ?string
    {
        return null;
    }
}
