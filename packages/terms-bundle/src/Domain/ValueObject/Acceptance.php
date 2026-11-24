<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;

/**
 * A member's acceptance of one version of a policy: who, which version, when, where, and — when the application keeps
 * it — the network the request came from, never the address itself. Acceptances are only ever added: a later one
 * stands beside the earlier ones, which remain the record of what was accepted when.
 *
 * The locale is the one whose text the member was shown, and the SHA-256 of the HTML that text renders to — 64
 * lowercase hexadecimal digits — proves which text that was. Both are null on an acceptance recorded without them.
 */
final readonly class Acceptance
{
    public const int MAX_NETWORK_LENGTH = 64;

    public const int SHA256_LENGTH = 64;

    /**
     * @throws InvalidTerms
     */
    public function __construct(
        public string $member,
        public string $policy,
        public int $version,
        public DateTimeImmutable $acceptedAt,
        public AcceptanceChannel $channel,
        public ?string $network = null,
        public ?string $locale = null,
        public ?string $textSha256 = null,
    ) {
        Identifiers::member($member);
        Identifiers::policy($policy);

        if ($version < 1 || $version > PolicyVersion::MAX_NUMBER) {
            throw InvalidTerms::because('version_number', 'A version number goes from 1 to 999999.');
        }

        if (null !== $network && ('' === $network || \strlen($network) > self::MAX_NETWORK_LENGTH)) {
            throw InvalidTerms::because('network', 'A network is kept as text of 1 to 64 bytes.');
        }

        if (null !== $locale) {
            Identifiers::locale($locale);
        }

        if (null !== $textSha256 && 1 !== preg_match('/^[0-9a-f]{64}$/D', $textSha256)) {
            throw InvalidTerms::because('text_sha256', 'The SHA-256 of a text is 64 lowercase hexadecimal digits.');
        }
    }
}
