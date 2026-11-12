<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\Http;

use Override;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The nonce a listener of the application has set on the main request, under the attribute the configuration names.
 */
final readonly class RequestAttributeNonce implements ScriptNonceProvider
{
    public function __construct(
        private RequestStack $requests,
        private string $attribute,
    ) {
    }

    #[Override]
    public function scriptNonce(): ?string
    {
        $nonce = $this->requests->getMainRequest()?->attributes->get($this->attribute);

        return \is_string($nonce) && '' !== $nonce ? $nonce : null;
    }
}
