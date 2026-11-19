<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Exception\InvalidIdentifier;
use Phil\GuardianConsent\Internal\Identifier;
use Stringable;

/**
 * The stable identifier of a guardian, such as a UUID, given by the application. It outlives an address and is copied
 * into every record, so an e-mail address is refused here: nothing with an `@` becomes an identifier.
 */
final readonly class GuardianId implements Stringable
{
    public const int MAX_LENGTH = Identifier::MAX_LENGTH;

    /** @var non-empty-string */
    public string $value;

    /**
     * @throws InvalidIdentifier when the identifier is empty, longer than 255 bytes, holds whitespace, a control
     *                           character or an `@`
     */
    public function __construct(string $value)
    {
        $this->value = Identifier::check($value, 'guardian');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
