<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use Override;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroomBundle\Application\Contract\CurrentParticipant;
use Phil\VirtualClassroomBundle\Domain\Model\Room;

/**
 * The participant of the request and their role, as the test says.
 */
final class FixedParticipant implements CurrentParticipant
{
    public function __construct(
        public ?Participant $participant = null,
        public Role $role = Role::Attendee,
    ) {
    }

    #[Override]
    public function participant(): ?Participant
    {
        return $this->participant;
    }

    #[Override]
    public function role(Room $room): Role
    {
        return $this->role;
    }
}
