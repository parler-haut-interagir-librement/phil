<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Http;

use Phil\Checkin\CheckpointId;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The configuration of the desk controller for one checkpoint: the endpoints' URLs when their routes are imported —
 * the offline ones only when the application has a catalog —, and the name of the cookie that carries the
 * controller's CSRF token.
 */
final readonly class DeskConfig
{
    public function __construct(
        private CheckinRequest $requests,
        private ?UrlGeneratorInterface $router = null,
        private bool $offline = false,
    ) {
    }

    /**
     * @return array{checkpoint: string, offline: bool, urls: ?array{scan: string, manifest: ?string, sync: ?string}, csrfCookie: string}
     */
    public function for(CheckpointId $checkpoint): array
    {
        $router = $this->router;

        return [
            'checkpoint' => $checkpoint->value,
            'offline' => $this->offline,
            'urls' => $router instanceof UrlGeneratorInterface ? [
                'scan' => $router->generate(Routes::NAMES['scan']),
                'manifest' => $this->offline ? $router->generate(Routes::NAMES['manifest']) : null,
                'sync' => $this->offline ? $router->generate(Routes::NAMES['sync']) : null,
            ] : null,
            'csrfCookie' => $this->requests->cookieName(),
        ];
    }
}
