<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use Phil\SamlSp\Binding\Binding;

/**
 * A message received from the browser, decoded: its XML, its binding, its relay state, whether its binding already
 * verified its signature (the signed query of HTTP-Redirect), and the parameter it came in, `SAMLRequest` or
 * `SAMLResponse`. A message whose binding did not verify it is verified by the signature it carries inside.
 */
final readonly class ReceivedMessage
{
    public function __construct(
        public string $xml,
        public Binding $binding,
        public ?string $relayState = null,
        public bool $signatureVerified = false,
        public string $parameter = 'SAMLResponse',
    ) {
    }

    public function isRequest(): bool
    {
        return 'SAMLRequest' === $this->parameter;
    }
}
