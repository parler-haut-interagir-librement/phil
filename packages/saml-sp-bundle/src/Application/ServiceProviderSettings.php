<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use SensitiveParameter;

/**
 * What the configuration says of the application as a service provider: its entity ID (the address of the metadata of
 * each identity provider when it names none), its signing and encryption keys in PEM, the name identifier format it asks
 * for, and whether its requests are signed.
 */
final readonly class ServiceProviderSettings
{
    /**
     * @param array{certificate: string, private_key: string, passphrase: string|null}|null  $signing
     * @param list<array{certificate: string, private_key: string, passphrase: string|null}> $encryption
     */
    public function __construct(
        public ?string $entityId,
        #[SensitiveParameter]
        public ?array $signing,
        #[SensitiveParameter]
        public array $encryption = [],
        public ?string $nameIdFormat = null,
        public bool $signRequests = true,
    ) {
    }

    /**
     * @return array<string, never>
     */
    public function __debugInfo(): array
    {
        return [];
    }
}
