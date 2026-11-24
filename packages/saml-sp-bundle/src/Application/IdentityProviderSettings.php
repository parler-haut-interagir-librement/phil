<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use InvalidArgumentException;
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Binding\Binding;

/**
 * What the configuration says of one identity provider: where its metadata comes from (an address, a file or the XML
 * itself, one of the three) and the certificates it must be signed with, the entity ID to read, the attribute map, the roles its groups
 * give, and what its responses must be.
 */
final readonly class IdentityProviderSettings
{
    /**
     * @param list<string>          $metadataCertificates the PEM certificates the metadata must be signed with
     * @param array<string, string> $groupRoles           a role of the application for each group the identity provider sends
     * @param list<string>          $defaultRoles         the roles every person of this identity provider gets
     */
    public function __construct(
        public string $key,
        public ?string $metadataUrl,
        public ?string $metadataXml,
        public ?string $metadataFile = null,
        public array $metadataCertificates = [],
        public ?string $entityId = null,
        public AttributeMap $attributes = new AttributeMap(),
        public array $groupRoles = [],
        public array $defaultRoles = ['ROLE_USER'],
        public bool $wantAssertionsSigned = true,
        public bool $wantAssertionsEncrypted = false,
        public bool $allowUnsolicited = false,
        public Binding $binding = Binding::Redirect,
        public bool $singleLogout = true,
        public bool $createAccounts = true,
        public bool $forceAuthn = false,
    ) {
        if (1 !== \count(array_filter([$metadataUrl, $metadataXml, $metadataFile], static fn (?string $source): bool => null !== $source))) {
            throw new InvalidArgumentException(\sprintf('The identity provider "%s" has its metadata from one of an address, a file or XML.', $key));
        }
    }
}
