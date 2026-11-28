<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * Something a member is owed each period: a postcard, a mention in the credits, a game with the game master.
 *
 * A benefit that ships an object needs a postal address, and has a shipping export; one that does not, a mention or a
 * game, is only checked off.
 */
final readonly class Benefit
{
    private const string CODE = '/^[a-z0-9][a-z0-9_.:-]{0,63}$/D';

    private function __construct(
        public string $code,
        public string $label,
        public bool $ships,
    ) {
    }

    /**
     * @param string $code  1 to 64 lower-case letters, digits, `_`, `.`, `:` or `-`; written in the shipping export,
     *                      so that it names the object, never the tier
     * @param string $label what a person reads, 1 to 255 characters
     */
    public static function of(string $code, string $label, bool $ships = false): self
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidDeliverable::because('A benefit is coded by 1 to 64 lower-case letters, digits, "_", ".", ":" or "-".');
        }

        $label = trim($label);

        if ('' === $label || mb_strlen($label) > 255) {
            throw InvalidDeliverable::because(\sprintf('The label of the benefit "%s" is 1 to 255 characters.', $code));
        }

        return new self($code, $label, $ships);
    }
}
