<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

use Phil\SafeHttp\AddressGuard;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SocialCards\Preview\LinkFinder;
use Phil\SocialCards\Preview\LinkPreview;
use Phil\SocialCards\Preview\PageTags;
use Phil\SocialCards\Preview\TagReader;
use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The previews of links to other sites: the page fetched by the guarded fetcher, its tags read, the preview kept in
 * the cache — a page that cannot be fetched too, as nothing, so that a dead link is not fetched on every view. The
 * preview of a pasted link is that of the first link of a text a member writes, as `firstIn()` reads it.
 */
final readonly class LinkPreviews
{
    public function __construct(
        private PageFetcher $fetcher,
        private TagReader $reader = new TagReader(),
        private ?CacheInterface $cache = null,
        private int $ttl = 86_400,
    ) {
    }

    public function preview(string $url): ?LinkPreview
    {
        if (!self::isFetchable($url)) {
            return null;
        }

        $read = function () use ($url): ?array {
            $tags = $this->tags($url);

            return $tags instanceof PageTags ? LinkPreview::fromTags($tags)->toArray() : null;
        };

        $kept = $this->cache instanceof CacheInterface
            ? $this->cache->get('phil_social_cards.preview.'.hash('xxh128', $url), function (ItemInterface $item) use ($read): ?array {
                $item->expiresAfter($this->ttl);

                return $read();
            })
            : $read();

        return \is_array($kept) ? LinkPreview::fromArray($kept) : null;
    }

    /**
     * The preview of the first link of a text that can be fetched, as a message shows it below its text: one link is
     * fetched at most, whatever the text holds.
     */
    public function firstIn(string $text): ?LinkPreview
    {
        foreach (LinkFinder::find($text) as $link) {
            if (self::isFetchable($link)) {
                return $this->preview($link);
            }
        }

        return null;
    }

    /**
     * The tags of the page, read where it was served from after its redirects.
     */
    public function tags(string $url): ?PageTags
    {
        $page = self::isFetchable($url) ? $this->fetcher->fetch($url) : null;

        return $page instanceof FetchedPage ? $this->reader->read($page->html, $page->url, $page->charset) : null;
    }

    /**
     * An address phil/safe-http reads: absolute HTTP or HTTPS, of a host name, without credentials, of 2,048
     * characters at most.
     */
    public static function isFetchable(string $url): bool
    {
        try {
            AddressGuard::address($url);
        } catch (RefusedAddress) {
            return false;
        }

        return true;
    }
}
