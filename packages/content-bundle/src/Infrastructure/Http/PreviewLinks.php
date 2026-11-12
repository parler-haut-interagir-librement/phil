<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Links to the draft of a content in one locale, signed and expiring, that an editor sends to whoever should read it
 * before it is published: nobody else can reach a draft.
 */
final readonly class PreviewLinks
{
    public const string ROUTE = 'phil_content_preview';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
        private int $ttl = 604_800,
    ) {
    }

    public function url(ContentReference $reference, Locale $locale): string
    {
        $expires = $this->now() + $this->ttl;

        return $this->urlGenerator->generate(self::ROUTE, [
            'reference' => $reference->toString(),
            'locale' => $locale->value,
            'expires' => $expires,
            'signature' => $this->signature($reference->toString(), $locale->value, $expires),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function verify(string $reference, string $locale, int $expires, string $signature): bool
    {
        return $expires >= $this->now() && hash_equals($this->signature($reference, $locale, $expires), $signature);
    }

    private function signature(string $reference, string $locale, int $expires): string
    {
        return hash_hmac('sha256', $reference.'|'.$locale.'|'.$expires, $this->secret);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
