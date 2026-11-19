<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Phil\GuardianConsent\Exception\InvalidConsent;
use SensitiveParameter;

/**
 * The holder of parental authority, or the tutor, asked to consent: a stable identifier the application gives, and
 * the address the confirmation is sent to.
 *
 * The address stays on the pending request only: once the guardian has answered, or the request is erased, it is gone;
 * a proof names the guardian by their identifier. The application checks the address the way it checks any other
 * before it comes here; the library only refuses what cannot be one.
 */
final readonly class Guardian
{
    public const int MAX_EMAIL_LENGTH = 254;

    /**
     * @throws InvalidConsent when the address is longer than 254 bytes, holds whitespace or a control character, or is
     *                        not one `@` between two non-empty parts
     */
    public function __construct(
        public GuardianId $id,
        #[SensitiveParameter]
        public string $email,
    ) {
        if (\strlen($email) > self::MAX_EMAIL_LENGTH || 1 !== preg_match('/^[^\s\x00-\x1F\x7F@]+@[^\s\x00-\x1F\x7F@]+$/D', $email)) {
            throw InvalidConsent::because('The address of a guardian is one "@" between two parts, without whitespace, at most 254 bytes.');
        }
    }
}
