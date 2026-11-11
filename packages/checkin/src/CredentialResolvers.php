<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Override;
use Phil\Checkin\Contract\CredentialResolver;

/**
 * Several resolvers asked in their order: the first that knows a code answers, and the ones after it are not asked.
 * With none, no code is known.
 */
final readonly class CredentialResolvers implements CredentialResolver
{
    /**
     * @param iterable<CredentialResolver> $resolvers
     */
    public function __construct(
        private iterable $resolvers = [],
    ) {
    }

    #[Override]
    public function resolve(CredentialCode $code): ?Credential
    {
        foreach ($this->resolvers as $resolver) {
            $credential = $resolver->resolve($code);

            if ($credential instanceof Credential) {
                return $credential;
            }
        }

        return null;
    }
}
