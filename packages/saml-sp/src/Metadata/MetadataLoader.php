<?php

declare(strict_types=1);

namespace Phil\SamlSp\Metadata;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\SamlSp\Contract\MetadataFetcher;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Key\Certificate;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Loads the metadata of an identity provider from XML or from its address, the fetched text kept in a cache for a
 * lifetime (a day by default) so that a sign-in never waits on the identity provider; metadata past its `validUntil`
 * is refused. `refresh` fetches it again, as the check command does.
 */
final readonly class MetadataLoader
{
    public function __construct(
        private MetadataFetcher $fetcher,
        private ClockInterface $clock,
        private ?CacheInterface $cache = null,
        private int $lifetime = 86_400,
        private MetadataReader $reader = new MetadataReader(),
    ) {
        if ($lifetime < 60 || $lifetime > 2_592_000) {
            throw new InvalidArgumentException(\sprintf('Metadata is cached 60 seconds to 30 days; %d seconds is not.', $lifetime));
        }
    }

    /**
     * @param list<Certificate> $trusted the certificates the metadata must be signed with; empty to read it unsigned
     */
    public function fromXml(string $xml, ?string $entityId = null, array $trusted = []): IdentityProviderMetadata
    {
        $metadata = $this->reader->read($xml, $entityId, $trusted);

        if ($metadata->validUntil instanceof DateTimeImmutable && $metadata->validUntil <= $this->clock->now()) {
            throw InvalidMetadata::because(\sprintf('The metadata of "%s" expired.', $metadata->entityId));
        }

        return $metadata;
    }

    /**
     * @param list<Certificate> $trusted
     */
    public function fromUrl(Url $url, ?string $entityId = null, array $trusted = [], bool $refresh = false): IdentityProviderMetadata
    {
        if (!$this->cache instanceof CacheInterface) {
            return $this->fromXml($this->fetcher->fetch($url), $entityId, $trusted);
        }

        $key = 'phil_saml_sp.metadata.'.hash('sha256', $url->toString());

        if ($refresh) {
            $this->cache->delete($key);
        }

        $xml = $this->cache->get($key, function (ItemInterface $item) use ($url): string {
            $item->expiresAfter($this->lifetime);

            return $this->fetcher->fetch($url);
        });

        try {
            return $this->fromXml($xml, $entityId, $trusted);
        } catch (InvalidMetadata $invalidMetadata) {
            $this->cache->delete($key);

            throw $invalidMetadata;
        }
    }
}
