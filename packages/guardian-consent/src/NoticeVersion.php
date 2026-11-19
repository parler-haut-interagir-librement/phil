<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Stringable;

/**
 * The version of the text shown to the person who consents, as the site names it — `2026-11`, `v3`, a hash of the
 * text. A proof keeps it, so that the words agreed to can be found again.
 *
 * A version is 1 to 64 characters: letters, digits, `.`, `_`, `-` and `+`.
 */
final readonly class NoticeVersion implements Stringable
{
    /**
     * @throws InvalidConsent when the version does not follow the rule
     */
    public function __construct(
        public string $value,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9._+-]{1,64}$/D', $value)) {
            throw InvalidConsent::because('The version of a text is 1 to 64 letters, digits, ".", "_", "-" or "+".');
        }
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
