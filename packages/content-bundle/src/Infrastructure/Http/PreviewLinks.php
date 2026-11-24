<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Links to the draft of a content in one locale, signed and expiring, that an editor sends to whoever should read it
 * before it is published: nobody else can reach a draft.
 *
 * The signature covers the content, the locale, the expiry and the scope the link was made in: the tenant of
 * `phil/tenancy-bundle` when the bundle has one active, the host of the link otherwise. A link opened in another
 * tenant, or on another host of an application without tenants, is refused, although the secret is shared and a
 * content of the same name exists there.
 *
 * The signature starts with its version, `v2.`, then the HMAC-SHA256 in hexadecimal; a signature of another version,
 * a link made before the scope was signed included, is refused.
 */
final readonly class PreviewLinks
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_content.preview_link';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public const string ROUTE = 'phil_content_preview';

    /**
     * The version of the signature, written before it: the format and the input it covers.
     */
    public const string VERSION = 'v2';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[SensitiveParameter]
        string $secret,
        private ClockInterface $clock,
        private int $ttl = 604_800,
        private ?TenantContext $tenants = null,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    /**
     * The link, absolute, on the host of the router's request context: the request's, or the console's default URI.
     */
    public function url(ContentReference $reference, Locale $locale): string
    {
        $expires = $this->now() + $this->ttl;

        return $this->urlGenerator->generate(self::ROUTE, [
            'reference' => $reference->toString(),
            'locale' => $locale->value,
            'expires' => $expires,
            'signature' => $this->signature($this->scope($this->urlGenerator->getContext()->getHost()), $reference->toString(), $locale->value, $expires),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Whether the link is signed, in this version and this scope, and not expired: `$host` is the host the link was
     * opened on.
     */
    public function verify(string $reference, string $locale, int $expires, string $signature, string $host): bool
    {
        return $expires >= $this->now()
            && hash_equals($this->signature($this->scope($host), $reference, $locale, $expires), $signature);
    }

    private function scope(string $host): string
    {
        $tenant = $this->tenants?->current();

        return $tenant instanceof TenantId ? 'tenant:'.$tenant->value : 'host:'.strtolower($host);
    }

    /**
     * Each part is written after its length, so that no two sets of parts give one input.
     */
    private function signature(string $scope, string $reference, string $locale, int $expires): string
    {
        $input = self::VERSION;

        foreach ([$scope, $reference, $locale] as $part) {
            $input .= '|'.\strlen($part).':'.$part;
        }

        return self::VERSION.'.'.hash_hmac('sha256', $input.'|'.$expires, $this->key);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
