<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Dns;

use Override;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\MailDomain;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps the answers of another resolver in a cache pool: a form submitted again, or a list of a thousand addresses at
 * the same provider, asks the DNS once. A lookup that failed, `MailDomain::Unknown`, is kept a short while only.
 */
final readonly class CachedMailDomainResolver implements MailDomainResolver
{
    /**
     * @param positive-int $ttl        how long an answer is kept, in seconds
     * @param positive-int $unknownTtl how long a failed lookup is kept, in seconds
     */
    public function __construct(
        private MailDomainResolver $resolver,
        private CacheInterface $cache,
        private int $ttl = 3600,
        private int $unknownTtl = 60,
    ) {
    }

    #[Override]
    public function resolve(string $asciiDomain): MailDomain
    {
        $answer = $this->cache->get('phil_email_address.mx.'.hash('xxh128', strtolower($asciiDomain)), function (ItemInterface $item) use ($asciiDomain): string {
            $mailDomain = $this->resolver->resolve($asciiDomain);
            $item->expiresAfter(MailDomain::Unknown === $mailDomain ? $this->unknownTtl : $this->ttl);

            return $mailDomain->value;
        });

        return MailDomain::tryFrom($answer) ?? MailDomain::Unknown;
    }
}
