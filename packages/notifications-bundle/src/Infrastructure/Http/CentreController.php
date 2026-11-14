<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Closure;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of the notification centre, for the recipient of the current request only: the unread count, and
 * marking one notification read, all of them read, or one archived. A change answers the controller, which asks for
 * JSON, with `{"unread": n}`, and a plain form with a redirection to the page it came from on this site, or to `/`.
 * A visitor gets a `403`, a request from another site a `403`, an unknown notification a `404`.
 */
final readonly class CentreController
{
    public function __construct(
        private NotificationRepository $repository,
        private CurrentRecipient $current,
        private CentreRequest $requests,
        private ClockInterface $clock,
    ) {
    }

    public function unread(): JsonResponse
    {
        $recipient = $this->current->id();

        if (null === $recipient) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['unread' => $this->repository->unreadCount($recipient)], headers: ['Cache-Control' => 'no-store']);
    }

    public function read(Request $request, string $id): Response
    {
        return $this->change($request, function (string $recipient) use ($id): bool {
            $notification = $this->repository->find($recipient, $id);
            $this->repository->markRead($recipient, $id, $this->clock->now());

            return $notification instanceof Notification;
        });
    }

    public function readAll(Request $request): Response
    {
        return $this->change($request, function (string $recipient): bool {
            $this->repository->markAllRead($recipient, $this->clock->now());

            return true;
        });
    }

    public function archive(Request $request, string $id): Response
    {
        return $this->change($request, function (string $recipient) use ($id): bool {
            $notification = $this->repository->find($recipient, $id);
            $this->repository->archive($recipient, $id);

            return $notification instanceof Notification;
        });
    }

    /**
     * @param Closure(string): bool $change says whether the notification was found
     */
    private function change(Request $request, Closure $change): Response
    {
        $recipient = $this->current->id();

        if (null === $recipient || !$this->requests->isTrusted($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$change($recipient)) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        if (\in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            return new JsonResponse(['unread' => $this->repository->unreadCount($recipient)]);
        }

        return new RedirectResponse($this->back($request), Response::HTTP_SEE_OTHER);
    }

    /**
     * The page the form was on, when it is on this site; the home page otherwise.
     */
    private function back(Request $request): string
    {
        $referer = $request->headers->get('Referer') ?? '';
        $site = $request->getSchemeAndHttpHost();

        return str_starts_with($referer, $site.'/') ? $referer : '/';
    }
}
