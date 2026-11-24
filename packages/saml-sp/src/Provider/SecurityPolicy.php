<?php

declare(strict_types=1);

namespace Phil\SamlSp\Provider;

use InvalidArgumentException;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Xml\XmlBudget;

/**
 * The checks of the messages the service provider receives: the clock skew allowed with the identity provider (two
 * minutes by default, ten at most), how old a response may be (ten minutes past its issue instant), what a signature
 * must be, and the bounds of a message.
 */
final readonly class SecurityPolicy
{
    public const int MAX_CLOCK_SKEW = 600;

    public function __construct(
        public int $clockSkew = 120,
        public int $maxAge = 600,
        public SignaturePolicy $signatures = new SignaturePolicy(),
        public XmlBudget $budget = new XmlBudget(),
    ) {
        if ($clockSkew < 0 || $clockSkew > self::MAX_CLOCK_SKEW) {
            throw new InvalidArgumentException(\sprintf('The clock skew is between 0 and %d seconds; %d is not.', self::MAX_CLOCK_SKEW, $clockSkew));
        }

        if ($maxAge < 30 || $maxAge > 86_400) {
            throw new InvalidArgumentException(\sprintf('The age of a response is between 30 and 86400 seconds; %d is not.', $maxAge));
        }
    }
}
