<?php

declare(strict_types=1);

namespace Phil\SamlSp\Provider;

use InvalidArgumentException;
use Phil\SamlSp\Key\KeyPair;
use Phil\Url\Url;

/**
 * The application as a SAML service provider for one identity provider: its entity ID, the address its assertions are
 * posted to (ACS), the address of its single logout (SLO), the key pair it signs with, the key pairs it decrypts with
 * (the current one first, the previous one during a rollover), the name identifier format it asks for, and whether its
 * requests are signed.
 */
final readonly class ServiceProvider
{
    /**
     * @param list<KeyPair> $encryption
     */
    public function __construct(
        public string $entityId,
        public Url $assertionConsumerService,
        public ?Url $singleLogoutService = null,
        public ?KeyPair $signing = null,
        public array $encryption = [],
        public ?string $nameIdFormat = null,
        public bool $signRequests = true,
    ) {
        if ('' === trim($entityId) || \strlen($entityId) > 1024) {
            throw new InvalidArgumentException('The entity ID of the service provider has 1 to 1024 characters.');
        }

        if ($signRequests && !$signing instanceof KeyPair) {
            throw new InvalidArgumentException('A service provider that signs its requests has a signing key pair.');
        }
    }
}
