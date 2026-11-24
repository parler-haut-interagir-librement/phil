<?php

declare(strict_types=1);

namespace Phil\SamlSp\Store;

use InvalidArgumentException;
use Override;
use Phil\SamlSp\Contract\PendingRequests;
use Phil\SamlSp\Message\PendingRequest;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps the pending requests in a PSR-6 cache pool, as arrays of scalars, for a lifetime (ten minutes by default):
 * the response of the identity provider may reach another worker or another server than the request.
 */
final readonly class CachePendingRequests implements PendingRequests
{
    public function __construct(private CacheItemPoolInterface $pool, private int $lifetime = 600)
    {
        if ($lifetime < 60 || $lifetime > 3600) {
            throw new InvalidArgumentException(\sprintf('A request waits 60 to 3600 seconds for its response; %d is not.', $lifetime));
        }
    }

    #[Override]
    public function save(PendingRequest $request): void
    {
        $this->pool->save($this->pool->getItem($this->key($request->id))->set($request->toArray())->expiresAfter($this->lifetime));
    }

    #[Override]
    public function take(string $id): ?PendingRequest
    {
        $key = $this->key($id);
        $item = $this->pool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $this->pool->deleteItem($key);
        $request = PendingRequest::fromArray($item->get());

        return $request instanceof PendingRequest && hash_equals($request->id, $id) ? $request : null;
    }

    private function key(string $id): string
    {
        return 'phil_saml_sp.request.'.hash('sha256', $id);
    }
}
