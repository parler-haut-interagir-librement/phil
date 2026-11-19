<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use Override;
use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\Contract\VirtualClassroom;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\Webhook;
use Throwable;

/**
 * A conferencing server played by the test: what it can do, what it answers, and every call it was given.
 */
final class FakeProvider implements VirtualClassroom
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<RoomSpec> */
    public array $opened = [];

    public ?Presences $presences = null;

    /** @var list<Recording> */
    public array $recordings = [];

    /** @var list<RoomEvent> */
    public array $events = [];

    public ?Throwable $failure = null;

    public ?Webhook $received = null;

    public function __construct(
        public Capabilities $capabilities = new Capabilities(true, true, true, true),
        public ?string $sessionKey = 'session-1',
    ) {
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    #[Override]
    public function open(RoomSpec $room): OpenedRoom
    {
        $this->calls[] = 'open '.$room->id->value;

        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->opened[] = $room;

        return new OpenedRoom($room->id, $room->record, $this->sessionKey);
    }

    #[Override]
    public function joinUrl(OpenedRoom $room, Participant $participant, Role $role): string
    {
        return 'https://meet.example.com/'.$room->id->value.'?as='.$participant->id->value.'&role='.$role->value.'&session='.$room->sessionKey;
    }

    #[Override]
    public function end(RoomId $room): void
    {
        $this->calls[] = 'end '.$room->value;
    }

    #[Override]
    public function presences(RoomId $room): Presences
    {
        $this->calls[] = 'presences '.$room->value;

        return $this->presences ?? throw Unsupported::because('No presences.');
    }

    #[Override]
    public function recordings(RoomId $room): array
    {
        $this->calls[] = 'recordings '.$room->value;

        return $this->recordings;
    }

    #[Override]
    public function receive(Webhook $webhook): array
    {
        $this->received = $webhook;

        if ('refuse' === $webhook->body) {
            throw InvalidWebhook::because('Refused.');
        }

        if ('unsupported' === $webhook->body) {
            throw Unsupported::because('No notifications.');
        }

        return $this->events;
    }
}
