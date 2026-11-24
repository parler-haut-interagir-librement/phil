<?php

declare(strict_types=1);

namespace Phil\SamlSp\Provider;

use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Metadata\IdentityProviderMetadata;

/**
 * An identity provider as the service provider trusts it: its metadata, the map of its attributes, and what is asked
 * of its responses. By default an assertion must be signed itself (a signed response around an unsigned assertion is
 * refused), an unsolicited response is refused, and encryption is accepted but not required.
 */
final readonly class IdentityProvider
{
    public function __construct(
        public IdentityProviderMetadata $metadata,
        public AttributeMap $attributes = new AttributeMap(),
        public bool $wantAssertionsSigned = true,
        public bool $wantAssertionsEncrypted = false,
        public bool $allowUnsolicited = false,
        public Binding $requestBinding = Binding::Redirect,
    ) {
    }
}
