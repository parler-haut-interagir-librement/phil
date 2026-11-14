<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Keeps a toast in the session's flash bag when the notification is for whoever makes the current request, for the
 * next page to show; a notification for someone else, or delivered by a worker, shows in the centre only. The toasts
 * component takes them out.
 */
final readonly class FlashToasts implements ToastPublisher
{
    public const string FLASH_TYPE = 'phil_notifications.toast';

    public function __construct(
        private RequestStack $requests,
        private CurrentRecipient $current,
    ) {
    }

    #[Override]
    public function publish(RenderedNotification $notification, int $unread): void
    {
        $flashes = $this->flashes();

        if (!$flashes instanceof FlashBagAwareSessionInterface || $notification->notification->recipient !== $this->current->id()) {
            return;
        }

        $flashes->getFlashBag()->add(self::FLASH_TYPE, $notification->toArray());
    }

    /**
     * Takes the waiting toasts out of the session.
     *
     * @return list<array{id: string, type: string, title: string, body: string, url: ?string, count: int}>
     */
    public function drain(): array
    {
        $toasts = [];

        foreach ($this->flashes()?->getFlashBag()->get(self::FLASH_TYPE) ?? [] as $toast) {
            if (\is_array($toast) && \is_string($toast['id'] ?? null) && \is_string($toast['title'] ?? null)) {
                $toasts[] = [
                    'id' => $toast['id'],
                    'type' => \is_string($toast['type'] ?? null) ? $toast['type'] : '',
                    'title' => $toast['title'],
                    'body' => \is_string($toast['body'] ?? null) ? $toast['body'] : '',
                    'url' => \is_string($toast['url'] ?? null) ? $toast['url'] : null,
                    'count' => \is_int($toast['count'] ?? null) ? $toast['count'] : 1,
                ];
            }
        }

        return $toasts;
    }

    private function flashes(): ?FlashBagAwareSessionInterface
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof FlashBagAwareSessionInterface ? $session : null;
    }
}
