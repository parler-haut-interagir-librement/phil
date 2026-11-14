<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Twig;

use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The Web Push button of the current recipient, `<twig:PhilNotifications:WebPush />`: it turns this browser's
 * notifications on and off, through the Stimulus controller, the service worker of the bundle and the endpoints of
 * `push_routes.php`. It shows nothing to a visitor, and stays hidden where the browser cannot receive them.
 */
#[AsTwigComponent(name: 'PhilNotifications:WebPush', template: '@PhilNotifications/components/WebPush.html.twig', exposePublicProps: false)]
final readonly class WebPushComponent
{
    /** The service worker, as AssetMapper serves it from the bundle. */
    public const string WORKER = 'phil-notifications-worker/push-worker.js';

    /**
     * @param ?string $worker the URL of the service worker; the bundle's, through the Asset component, when null
     */
    public function __construct(
        private CentreView $view,
        private string $publicKey,
        private ?UrlGeneratorInterface $router = null,
        private ?Packages $packages = null,
        private ?string $worker = null,
    ) {
    }

    public function isVisible(): bool
    {
        return null !== $this->view->recipient() && $this->router instanceof UrlGeneratorInterface && null !== $this->workerUrl();
    }

    /**
     * @return array{urls: array<string, string>, token: ?string, header: string, key: string, worker: ?string}
     */
    public function getConfig(): array
    {
        $urls = [];

        foreach (Routes::PUSH as $action => $route) {
            $urls[$action] = $this->router?->generate($route) ?? '';
        }

        return [
            'urls' => $urls,
            'token' => $this->view->token(),
            'header' => CentreRequest::HEADER,
            'key' => $this->publicKey,
            'worker' => $this->workerUrl(),
        ];
    }

    private function workerUrl(): ?string
    {
        return $this->worker ?? $this->packages?->getUrl(self::WORKER);
    }
}
