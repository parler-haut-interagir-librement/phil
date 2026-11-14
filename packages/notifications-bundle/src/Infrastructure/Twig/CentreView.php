<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Twig;

use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\FlashToasts;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Phil\NotificationsBundle\Infrastructure\Mercure\MercureToasts;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What the components show of the current recipient's centre: their unread count, their latest notifications written
 * out in their locale, the toasts waiting in their session, and the configuration of the Stimulus controller — the
 * endpoints' URLs, the CSRF token, and the subscription URL of their own topic on the Mercure hub.
 */
final readonly class CentreView
{
    public function __construct(
        private NotificationRepository $repository,
        private CurrentRecipient $current,
        private NotificationRenderer $renderer,
        private NotificationPreferences $preferences,
        private ?CentreRequest $requests = null,
        private ?UrlGeneratorInterface $router = null,
        private ?HubInterface $hub = null,
        private string $prefix = MercureToasts::PREFIX,
        private ?FlashToasts $flashes = null,
    ) {
    }

    public function recipient(): ?string
    {
        return $this->current->id();
    }

    public function unread(): int
    {
        $recipient = $this->current->id();

        return null === $recipient ? 0 : $this->repository->unreadCount($recipient);
    }

    /**
     * @param positive-int $limit
     *
     * @return list<RenderedNotification>
     */
    public function latest(int $limit): array
    {
        $recipient = $this->current->id();

        if (null === $recipient) {
            return [];
        }

        $locale = $this->preferences->settingsFor($recipient)->locale;

        return array_map(fn (Notification $n): RenderedNotification => $this->renderer->render($n, $locale), $this->repository->latest($recipient, $limit));
    }

    /**
     * @return list<array{id: string, type: string, title: string, body: string, url: ?string, count: int}>
     */
    public function toasts(): array
    {
        return $this->flashes?->drain() ?? [];
    }

    public function token(): ?string
    {
        return $this->requests?->token();
    }

    /**
     * @return array{urls: ?array<string, string>, token: ?string, header: string, hub: ?string}
     */
    public function config(): array
    {
        $recipient = $this->current->id();
        $subscription = null;

        if ($this->hub instanceof HubInterface && null !== $recipient) {
            $url = $this->hub->getPublicUrl();
            $parameter = ProtocolVersion::Legacy === $this->hub->getProtocolVersion() ? 'topic' : 'match';
            $subscription = $url.(str_contains($url, '?') ? '&' : '?').$parameter.'='.rawurlencode($this->prefix.$recipient);
        }

        return [
            'urls' => $this->router instanceof UrlGeneratorInterface ? [
                'unread' => $this->router->generate(Routes::NAMES['unread']),
                'readAll' => $this->router->generate(Routes::NAMES['readAll']),
            ] : null,
            'token' => $this->token(),
            'header' => CentreRequest::HEADER,
            'hub' => $subscription,
        ];
    }
}
