<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application\Contract;

use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroomBundle\Domain\Model\Room;

/**
 * Who joins in the request — their stable identifier and the name the room shows —, or null when nobody may, and the
 * role they take in a room.
 */
interface CurrentParticipant
{
    public function participant(): ?Participant;

    public function role(Room $room): Role;
}
