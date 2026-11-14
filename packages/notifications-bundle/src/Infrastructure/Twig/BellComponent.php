<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Twig;

use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bell of the current recipient, `<twig:PhilNotifications:Bell />`: a button with their unread count that opens
 * their latest notifications, and the Stimulus controller that keeps the count and the toasts live.
 */
#[AsTwigComponent(name: 'PhilNotifications:Bell', template: '@PhilNotifications/components/Bell.html.twig', exposePublicProps: false)]
final class BellComponent
{
    /** @var positive-int how many notifications the panel lists */
    public int $limit = 10;

    public function __construct(
        private readonly CentreView $view,
    ) {
    }

    public function getUnread(): int
    {
        return $this->view->unread();
    }

    /**
     * @return list<RenderedNotification>
     */
    public function getNotifications(): array
    {
        return $this->view->latest($this->limit);
    }

    public function getToken(): ?string
    {
        return $this->view->token();
    }

    /**
     * @return array{urls: ?array<string, string>, token: ?string, header: string, hub: ?string}
     */
    public function getConfig(): array
    {
        return $this->view->config();
    }

    public function isVisible(): bool
    {
        return null !== $this->view->recipient();
    }
}
