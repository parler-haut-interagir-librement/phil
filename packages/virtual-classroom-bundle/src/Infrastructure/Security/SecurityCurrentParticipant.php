<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Security;

use Override;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroomBundle\Application\Contract\CurrentParticipant;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated user of Symfony Security: their user identifier names them and is the name the room shows, and
 * the role the configuration names makes them a moderator. A user identifier that is an e-mail address names nobody:
 * it would travel to the server and back; an application whose users sign in by e-mail names its own service.
 */
final readonly class SecurityCurrentParticipant implements CurrentParticipant
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private AuthorizationCheckerInterface $authorization,
        private string $moderatorRole,
    ) {
    }

    #[Override]
    public function participant(): ?Participant
    {
        $user = $this->tokens->getToken()?->getUser();
        $id = $user instanceof UserInterface ? ParticipantId::tryFrom($user->getUserIdentifier()) : null;

        if (!$id instanceof ParticipantId) {
            return null;
        }

        try {
            return new Participant($id, $id->value);
        } catch (InvalidValue) {
            return null;
        }
    }

    #[Override]
    public function role(Room $room): Role
    {
        return $this->authorization->isGranted($this->moderatorRole, $room) ? Role::Moderator : Role::Attendee;
    }
}
