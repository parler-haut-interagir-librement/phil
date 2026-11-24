<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;

/**
 * A response whose status is not success: the identity provider did not sign the person in. It carries the status code
 * and the second-level code when there is one, such as `urn:oasis:names:tc:SAML:2.0:status:AuthnFailed`.
 */
final class IdentityProviderRefused extends RuntimeException implements SamlException
{
    public function __construct(string $message, public readonly string $status, public readonly ?string $subStatus)
    {
        parent::__construct($message);
    }

    public static function withStatus(string $status, ?string $subStatus): self
    {
        return new self(
            \sprintf('The identity provider answered the status "%s"%s.', $status, null === $subStatus ? '' : \sprintf(' (%s)', $subStatus)),
            $status,
            $subStatus,
        );
    }
}
