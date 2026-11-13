<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\ValueObject;

use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;

/**
 * One answer a poll offers: the identifier ballots name it by, 1 to 64 ASCII letters, digits, `_` or `-`, and the
 * label shown to voters, a UTF-8 text of 1 to 200 characters.
 */
final readonly class PollOption
{
    public const int MAX_LABEL = 200;

    private function __construct(
        public string $id,
        public string $label,
    ) {
    }

    /**
     * @throws InvalidPollDefinition when the identifier or the label is out of bounds
     */
    public static function of(string $id, string $label): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id)) {
            throw InvalidPollDefinition::because(\sprintf('An option is identified by 1 to %d ASCII letters, digits, "_" or "-".', PollRules::MAX_OPTION_LENGTH));
        }

        if ('' === trim($label) || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > self::MAX_LABEL) {
            throw InvalidPollDefinition::because(\sprintf('The label of an option is a UTF-8 text of 1 to %d characters.', self::MAX_LABEL));
        }

        return new self($id, $label);
    }
}
