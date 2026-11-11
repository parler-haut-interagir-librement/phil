<?php

declare(strict_types=1);

namespace Phil\InstantMessenger\Tests\Unit\Fixtures;

use Override;
use Phil\InstantMessenger\MessengerType;

/**
 * An application's own service, for the tests: a forum whose handles are written `forum:name`.
 */
enum ForumMessenger: string implements MessengerType
{
    case Forum = 'forum';

    #[Override]
    public function normalize(string $input): ?string
    {
        return 1 === preg_match('/^forum:([a-z]{3,20})$/D', $input, $match) ? $match[1] : null;
    }

    #[Override]
    public function display(string $handle): string
    {
        return 'forum:'.$handle;
    }

    #[Override]
    public function link(string $handle): string
    {
        return 'https://forum.example/u/'.$handle;
    }
}
