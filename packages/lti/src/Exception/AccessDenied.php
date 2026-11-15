<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;

/**
 * A call to a service of the platform without a valid access token, or with one that lacks the scope the call needs.
 */
final class AccessDenied extends RuntimeException implements LtiException
{
    public function __construct(
        string $message,
        public readonly bool $insufficientScope,
    ) {
        parent::__construct($message);
    }

    public static function invalidToken(): self
    {
        return new self('The access token is missing, unknown or expired.', false);
    }

    public static function insufficientScope(string $scope): self
    {
        return new self(\sprintf('The access token does not grant the scope "%s".', $scope), true);
    }
}
