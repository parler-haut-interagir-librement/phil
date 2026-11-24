<?php

declare(strict_types=1);

namespace Phil\SamlSp\Metadata;

use DateTimeImmutable;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Key\Certificate;
use Phil\Url\Url;

/**
 * What the service provider knows of an identity provider: its entity ID, where it signs people in and out by binding,
 * the certificates its signatures verify with (several during a rollover), whether it wants signed requests, and until
 * when the metadata is valid.
 */
final readonly class IdentityProviderMetadata
{
    /**
     * @param array<value-of<Binding>, Url> $singleSignOnServices
     * @param array<value-of<Binding>, Url> $singleLogoutServices
     * @param list<Certificate>             $signingCertificates
     */
    public function __construct(
        public string $entityId,
        public array $singleSignOnServices,
        public array $singleLogoutServices,
        public array $signingCertificates,
        public bool $wantAuthnRequestsSigned = false,
        public ?DateTimeImmutable $validUntil = null,
    ) {
        if ('' === trim($entityId) || [] === $singleSignOnServices || [] === $signingCertificates) {
            throw InvalidMetadata::because('An identity provider has an entity ID, a sign-on service on HTTP-Redirect or HTTP-POST and a signing certificate.');
        }

        foreach (array_keys($singleSignOnServices + $singleLogoutServices) as $binding) {
            Binding::from($binding);
        }
    }

    /**
     * The sign-on service on the preferred binding, else on the other.
     *
     * @return array{Binding, Url}
     */
    public function singleSignOn(Binding $preferred): array
    {
        $binding = isset($this->singleSignOnServices[$preferred->value]) ? $preferred : Binding::from((string) array_key_first($this->singleSignOnServices));

        return [$binding, $this->singleSignOnServices[$binding->value]];
    }

    /**
     * The logout service on the preferred binding, else on the other, null when there is none.
     *
     * @return array{Binding, Url}|null
     */
    public function singleLogout(Binding $preferred): ?array
    {
        return $this->pick($this->singleLogoutServices, $preferred);
    }

    /**
     * @param array<value-of<Binding>, Url> $services
     *
     * @return array{Binding, Url}|null
     */
    private function pick(array $services, Binding $preferred): ?array
    {
        foreach ([$preferred, Binding::Redirect === $preferred ? Binding::Post : Binding::Redirect] as $binding) {
            if (isset($services[$binding->value])) {
                return [$binding, $services[$binding->value]];
            }
        }

        return null;
    }
}
