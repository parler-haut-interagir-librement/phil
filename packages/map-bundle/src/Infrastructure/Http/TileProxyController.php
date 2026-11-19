<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Http;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\Exception\TileUnavailable;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The tile proxy: a tile of the source, fetched by the site under its own user agent and kept in a cache pool, so that
 * the source sees the site and never the visitor — no address, cookie nor referrer of theirs is forwarded.
 *
 * Only a tile of the source's grid is fetched; the answer is an image of a type the proxy accepts, no larger than its
 * limit, without redirects. A failure is not kept: the next request asks again. Nothing is held between requests.
 */
final readonly class TileProxyController
{
    public const array TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(
        private TileSource $source,
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private string $userAgent,
        private int $ttl = 604_800,
        private int $maxBytes = 524_288,
        private float $timeout = 5.0,
    ) {
    }

    public function __invoke(int $z, int $x, int $y): Response
    {
        if (!$this->source->has($z, $x, $y)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        /** @var ?array{type: string, body: string} $tile */
        $tile = $this->cache->get('phil_map_tile.'.hash('xxh128', $this->source->template).'.'.$z.'.'.$x.'.'.$y, function (ItemInterface $item, bool &$save) use ($z, $x, $y): ?array {
            try {
                return $this->fetch($item, $z, $x, $y);
            } catch (TileUnavailable|InvalidMap) {
                $save = false;

                return null;
            }
        });

        if (null === $tile) {
            return new Response('', Response::HTTP_BAD_GATEWAY, ['Cache-Control' => 'no-store']);
        }

        $response = new Response($tile['body'], Response::HTTP_OK, ['Content-Type' => $tile['type'], 'X-Content-Type-Options' => 'nosniff']);
        $response->setPublic();
        $response->setMaxAge($this->ttl);

        return $response;
    }

    /**
     * @return array{type: string, body: string}
     *
     * @throws TileUnavailable when the source does not answer with an image the proxy accepts
     * @throws InvalidMap      when the template gives no address for the tile
     */
    private function fetch(ItemInterface $item, int $z, int $x, int $y): array
    {
        $item->expiresAfter($this->ttl);
        $limit = $this->maxBytes;

        try {
            $response = $this->http->request('GET', $this->source->url($z, $x, $y)->toString(), [
                'headers' => ['User-Agent' => $this->userAgent, 'Accept' => implode(', ', self::TYPES)],
                'max_redirects' => 0,
                'timeout' => $this->timeout,
                'on_progress' => static function (int $downloaded) use ($limit): void {
                    if ($downloaded > $limit) {
                        throw TileUnavailable::because('The tile is larger than the limit.');
                    }
                },
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw TileUnavailable::because(\sprintf('The source answered %d.', $response->getStatusCode()));
            }

            $type = strtolower(trim(explode(';', $response->getHeaders()['content-type'][0] ?? '')[0]));

            if (!\in_array($type, self::TYPES, true)) {
                throw TileUnavailable::because(\sprintf('The source answered "%s", not an image.', $type));
            }

            $body = $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw TileUnavailable::because($exception->getMessage());
        }

        if (\strlen($body) > $limit) {
            throw TileUnavailable::because('The tile is larger than the limit.');
        }

        return ['type' => $type, 'body' => $body];
    }
}
