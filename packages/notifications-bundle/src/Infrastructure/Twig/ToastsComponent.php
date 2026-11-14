<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Twig;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The region the toasts appear in, `<twig:PhilNotifications:Toasts />`, once per layout: the toasts the session kept
 * for this page, and those Mercure brings while it is open.
 */
#[AsTwigComponent(name: 'PhilNotifications:Toasts', template: '@PhilNotifications/components/Toasts.html.twig', exposePublicProps: false)]
final readonly class ToastsComponent
{
    public function __construct(
        private CentreView $view,
    ) {
    }

    /**
     * @return list<array{id: string, type: string, title: string, body: string, url: ?string, count: int}>
     */
    public function getToasts(): array
    {
        return $this->view->toasts();
    }
}
