<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit\Fixture;

use Override;
use Phil\InstantMessenger\MessengerType;

/**
 * A messaging service whose value is not its name, as an application's own enum can have.
 */
enum ChatMessenger: string implements MessengerType
{
    case Mattermost = 'mm';

    #[Override]
    public function normalize(string $input): string
    {
        return $input;
    }

    #[Override]
    public function display(string $handle): string
    {
        return $handle;
    }

    #[Override]
    public function link(string $handle): ?string
    {
        return null;
    }
}
