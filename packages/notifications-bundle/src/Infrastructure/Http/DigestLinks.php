<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The links of a digest, absolute: the application's page of notification preferences, when its route is configured,
 * and the signed link that stops a recipient's digest, valid for a year, when a `DigestUnsubscriber` records it.
 *
 * The links are written in a worker: the router reads the scheme and the host of `framework.router.default_uri`. When
 * the application did not import `digest_routes.php`, the digest leaves without its link to unsubscribe, and an error
 * is logged.
 */
final readonly class DigestLinks
{
    public const string LIFETIME = 'P1Y';

    public function __construct(
        private UrlGeneratorInterface $router,
        private ?UriSigner $signer = null,
        private ?string $preferencesRoute = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function preferences(): ?string
    {
        return null === $this->preferencesRoute ? null : $this->router->generate($this->preferencesRoute, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function unsubscribe(string $recipient): ?string
    {
        if (!$this->signer instanceof UriSigner) {
            return null;
        }

        try {
            $url = $this->router->generate(Routes::DIGEST_UNSUBSCRIBE, ['recipient' => $recipient], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            $this->logger?->error('A digest left without its link to unsubscribe: the application does not import the route {route}, from digest_routes.php.', ['route' => Routes::DIGEST_UNSUBSCRIBE]);

            return null;
        }

        return $this->signer->sign($url, new DateInterval(self::LIFETIME));
    }
}
