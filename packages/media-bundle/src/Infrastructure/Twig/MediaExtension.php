<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Twig;

use Phil\MediaBundle\Application\MediaSchema;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * `media_url(media)`, `media_url(media, 'thumbnail', 'avif')`, `media_url(media, 'thumbnail', 'avif', 640)`,
 * `media_url(media, 'original', 'webp')`: the signed URL of a medium's file, of a format, or of one of its narrower
 * widths, valid for the configured time, or forever; `media_schema(media)`: the medium as schema.org data.
 *
 * With a time to live, the moment a URL expires is rounded up to a step — a tenth of the time to live, an hour at
 * most —, so that every page rendered during one step hands out the same URL, which a browser or a CDN can keep: a URL
 * stays valid from the time to live to the time to live and one step.
 */
final readonly class MediaExtension
{
    /**
     * @param int<1, max>|null $ttl how many seconds a URL stays valid at least; null, forever
     */
    public function __construct(
        private UrlGeneratorInterface $urls,
        private UrlSigner $signer,
        private ClockInterface $clock,
        private ?int $ttl = null,
    ) {
    }

    #[AsTwigFunction(name: 'media_url')]
    public function url(Media $media, string $format = 'original', ?string $type = null, ?int $width = null): string
    {
        $parameters = ['id' => $media->id, 'version' => $media->version(), 'format' => $format, 'type' => $type ?? 'webp'];
        $path = match (true) {
            null !== $width => $this->urls->generate('phil_media_format_width', [...$parameters, 'width' => $width]),
            'original' !== $format || null !== $type => $this->urls->generate('phil_media_format', $parameters),
            default => $this->urls->generate('phil_media_original', ['id' => $media->id, 'version' => $media->version()]),
        };
        $expires = null === $this->ttl ? null : $this->expires($this->ttl);
        $base = $this->urls->getContext()->getBaseUrl();
        $signed = substr($path, \strlen($base));

        return $path.'?'.http_build_query(['expires' => $expires, 'signature' => $this->signer->signature($signed, $expires)]);
    }

    /**
     * @param int<1, max> $ttl
     */
    private function expires(int $ttl): int
    {
        $step = max(1, min(3600, intdiv($ttl, 10)));

        return intdiv($this->clock->now()->getTimestamp() + $ttl + $step - 1, $step) * $step;
    }

    /**
     * The medium as schema.org JSON-LD data — `ImageObject` or `MediaObject` —, its file at an absolute signed URL.
     *
     * @return array<string, int|string>
     */
    #[AsTwigFunction(name: 'media_schema')]
    public function schema(Media $media): array
    {
        $context = $this->urls->getContext();
        $secure = 'https' === $context->getScheme();
        $port = $secure ? $context->getHttpsPort() : $context->getHttpPort();
        $origin = $context->getScheme().'://'.$context->getHost().(($secure ? 443 : 80) === $port ? '' : ':'.$port);

        return MediaSchema::of($media, $origin.$this->url($media));
    }
}
