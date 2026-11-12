<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Identity;

/**
 * Who signed in, as the provider asserted it: the issuer, the subject and the claims the application asked to keep.
 *
 * An `email` or a `phone_number` the provider does not state as verified is left out, so that no code downstream can
 * link an account on it.
 */
final readonly class VerifiedIdentity
{
    private const array VERIFICATION_CLAIMS = ['email' => 'email_verified', 'phone_number' => 'phone_number_verified'];

    /**
     * @param array<string, mixed> $claims
     */
    private function __construct(
        public string $issuer,
        public string $subject,
        public array $claims,
    ) {
    }

    /**
     * @param array<array-key, mixed> $claims  everything the provider asserted
     * @param list<string>            $allowed the names of the claims to keep
     */
    public static function fromClaims(string $issuer, string $subject, array $claims, array $allowed): self
    {
        $kept = [];
        foreach ($allowed as $name) {
            if (\array_key_exists($name, $claims) && self::isTrustworthy($name, $claims)) {
                $kept[$name] = $claims[$name];
            }
        }

        return new self($issuer, $subject, $kept);
    }

    public function verifiedEmail(): ?string
    {
        $email = $this->claims['email'] ?? null;

        return \is_string($email) && '' !== $email ? $email : null;
    }

    /**
     * @param array<array-key, mixed> $claims
     */
    private static function isTrustworthy(string $name, array $claims): bool
    {
        return !isset(self::VERIFICATION_CLAIMS[$name]) || true === ($claims[self::VERIFICATION_CLAIMS[$name]] ?? null);
    }
}
