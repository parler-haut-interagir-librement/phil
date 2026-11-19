<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use Override;
use Phil\Url\Url;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\CallbackUrls;

/**
 * Callback addresses written plainly, so that a test reads them.
 */
final readonly class FixedCallbacks implements CallbackUrls
{
    #[Override]
    public function ended(string $provider, RoomId $room): Url
    {
        return Url::fromString('https://app.example.com/ended/'.$provider.'/'.$room->value);
    }

    #[Override]
    public function events(string $provider, RoomId $room): Url
    {
        return Url::fromString('https://app.example.com/events/'.$provider.'/'.$room->value);
    }
}
