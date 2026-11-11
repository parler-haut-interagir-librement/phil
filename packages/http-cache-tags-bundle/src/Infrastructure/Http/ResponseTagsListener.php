<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Http;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag as TagValue;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds the tags of a controller's `#[CacheTag]`, and writes the tags of the response into the header the cache in
 * front reads — `xkey` for Varnish, `Cache-Tag` for Cloudflare, `Surrogate-Key` for Fastly —, on the main request.
 *
 * A placeholder takes the value of a request attribute, which comes from the URL: a tag whose attribute is missing, or
 * whose value makes it no tag — an accent, a space —, is left out, so that a visitor's URL never turns into an error.
 */
final readonly class ResponseTagsListener
{
    public function __construct(
        private ResponseTags $tags,
        private string $header = 'xkey',
        private string $separator = ' ',
    ) {
    }

    public function onController(ControllerEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;

        foreach ($event->getAttributes(CacheTag::class) as $attribute) {
            foreach ($attribute->tags as $tag) {
                if (1 !== preg_match('/\{\w+\}/', $tag)) {
                    // No placeholder: a tag the developer wrote, checked as such.
                    $this->tags->add($tag);

                    continue;
                }

                $missing = false;
                $resolved = preg_replace_callback('/\{(\w+)\}/', static function (array $match) use ($attributes, &$missing): string {
                    $value = $attributes->get($match[1]);
                    $missing = $missing || !\is_scalar($value);

                    return \is_scalar($value) ? (string) $value : '';
                }, $tag);

                if (!$missing && null !== $resolved && TagValue::tryOf($resolved) instanceof TagValue) {
                    $this->tags->add($resolved);
                }
            }
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tags = $this->tags->all();
        $this->tags->reset();

        if ([] !== $tags) {
            $event->getResponse()->headers->set($this->header, implode($this->separator, $tags));
        }
    }
}
