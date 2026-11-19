<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Http;

use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\VirtualClassroomException;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\CurrentParticipant;
use Phil\VirtualClassroomBundle\Domain\Exception\ClassroomException;
use Phil\VirtualClassroomBundle\Domain\Exception\RoomClosed;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownRoom;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Sends the participant of the request to the room: the room opens on its server if needed, and the answer is a
 * redirection to the link written for their role, never cached, never named to the next site. Nobody to name is a
 * 403, an unknown room a 404, an ended room a 410, a server out of reach a 503 to retry in 30 seconds.
 */
final readonly class JoinController
{
    public const string ROUTE = 'phil_virtual_classroom_join';

    public function __construct(
        private Classrooms $classrooms,
        private CurrentParticipant $current,
    ) {
    }

    public function __invoke(string $room): RedirectResponse
    {
        try {
            $id = RoomId::fromString($room);
        } catch (InvalidValue) {
            throw new NotFoundHttpException('No room is named so.');
        }

        $participant = $this->current->participant();

        if (!$participant instanceof Participant) {
            throw new AccessDeniedHttpException('Nobody of the request may join a room.');
        }

        try {
            $url = $this->classrooms->joinUrl($id, $participant, $this->current->role($this->classrooms->room($id)));
        } catch (UnknownRoom $unknown) {
            throw new NotFoundHttpException($unknown->getMessage(), $unknown);
        } catch (RoomClosed $closed) {
            throw new GoneHttpException($closed->getMessage(), $closed);
        } catch (VirtualClassroomException|ClassroomException $unavailable) {
            throw new ServiceUnavailableHttpException(30, 'The conferencing server cannot open the room now.', $unavailable);
        }

        $response = new RedirectResponse($url, Response::HTTP_SEE_OTHER);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
