<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Contract;

use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\Webhook;

/**
 * A conferencing server: it opens a room, writes the link a participant joins it by with their role, ends it, says
 * who is in it, lists its recordings and reads its notifications. An adapter does what its server allows and says so
 * in `capabilities()`; it keeps no state between calls, so that one instance serves every request of a worker.
 */
interface VirtualClassroom
{
    public function capabilities(): Capabilities;

    /**
     * Opens the room, or finds it open: calling it again for a running room changes nothing.
     *
     * @throws ProviderUnavailable when the server cannot be reached in time
     * @throws ProviderRefused     when the server refuses the room
     * @throws InvalidResponse     when its answer cannot be read
     */
    public function open(RoomSpec $room): OpenedRoom;

    /**
     * The link the participant joins the room by, signed for their role; the server is not called.
     */
    public function joinUrl(OpenedRoom $room, Participant $participant, Role $role): string;

    /**
     * Ends the room for everyone in it; a room already gone is not an error.
     *
     * @throws Unsupported         when the server has no such call
     * @throws ProviderUnavailable when the server cannot be reached in time
     * @throws ProviderRefused     when the server refuses the call
     * @throws InvalidResponse     when its answer cannot be read
     */
    public function end(RoomId $room): void;

    /**
     * Who is in the room now.
     *
     * @throws Unsupported         when the server cannot say
     * @throws ProviderUnavailable when the server cannot be reached in time
     * @throws ProviderRefused     when the server refuses the call
     * @throws InvalidResponse     when its answer cannot be read
     */
    public function presences(RoomId $room): Presences;

    /**
     * The recordings of the room the server keeps.
     *
     * @return list<Recording>
     *
     * @throws Unsupported         when the server cannot list them
     * @throws ProviderUnavailable when the server cannot be reached in time
     * @throws ProviderRefused     when the server refuses the call
     * @throws InvalidResponse     when its answer cannot be read
     */
    public function recordings(RoomId $room): array;

    /**
     * What a notification of the server says, once its signature is checked.
     *
     * @return list<RoomEvent> in the order they happened; empty for a notification of something else
     *
     * @throws Unsupported    when the server sends no notifications
     * @throws InvalidWebhook when the notification is refused
     */
    public function receive(Webhook $webhook): array;
}
