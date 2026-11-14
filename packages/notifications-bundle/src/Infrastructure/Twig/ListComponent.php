<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Twig;

use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The latest notifications of the current recipient on a page of their own, `<twig:PhilNotifications:List limit="50" />`,
 * each with its buttons to mark it read and to archive it.
 */
#[AsTwigComponent(name: 'PhilNotifications:List', template: '@PhilNotifications/components/List.html.twig', exposePublicProps: false)]
final class ListComponent
{
    /** @var positive-int */
    public int $limit = 20;

    public function __construct(
        private readonly CentreView $view,
    ) {
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
}
