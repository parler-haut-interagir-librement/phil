<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Http;

use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The configuration of the collaboration controller for one resource and one holder: the endpoints' URLs when their
 * routes are imported, the hub's subscription URL and topic when Mercure is installed — the topic in `match` for a hub
 * of the Mercure 1.0 protocol, in `topic` for an older one —, the lease's duration, whether to offer the takeover
 * of another holder's block, and the name of the cookie that carries the controller's CSRF token.
 */
final readonly class ClientConfig
{
    public function __construct(
        private int $seconds,
        private ?UrlGeneratorInterface $router = null,
        private ?HubInterface $hub = null,
        private string $prefix = '',
        private ?EditLockRequest $requests = null,
    ) {
    }

    /**
     * @return array{resource: string, holder: array{id: string, name: string}, seconds: int, takeOver: bool, urls: ?array<string, string>, topic: ?string, hub: ?string, csrfCookie: ?string}
     */
    public function for(string $resource, Holder $holder, bool $takeOver = false): array
    {
        $key = LeaseKey::of($resource);
        $topic = null;
        $subscription = null;

        if ($this->hub instanceof HubInterface) {
            $topic = $this->prefix.$key->resource;
            $url = $this->hub->getPublicUrl();
            $parameter = ProtocolVersion::Legacy === $this->hub->getProtocolVersion() ? 'topic' : 'match';
            $subscription = $url.(str_contains($url, '?') ? '&' : '?').$parameter.'='.rawurlencode($topic);
        }

        return [
            'resource' => $key->resource,
            'holder' => ['id' => $holder->id, 'name' => $holder->name],
            'seconds' => $this->seconds,
            'takeOver' => $takeOver,
            'urls' => $this->router instanceof UrlGeneratorInterface ? array_map($this->router->generate(...), Routes::NAMES) : null,
            'topic' => $topic,
            'hub' => $subscription,
            'csrfCookie' => $this->requests?->cookieName(),
        ];
    }
}
