<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * The option values of a line do not suit the sellable: an option it does not have, a required one missing, or a
 * value its option refuses. `$option` names the option, for a form to show the error beside its field.
 */
final class InvalidConfiguration extends DomainException implements DomainError
{
    public function __construct(
        public readonly string $option,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownOption(string $option): self
    {
        return new self($option, \sprintf("The option %s is not one of the sellable's.", $option));
    }

    public static function required(string $option): self
    {
        return new self($option, \sprintf('The option %s is required.', $option));
    }

    public static function refused(string $option, string $reason): self
    {
        return new self($option, \sprintf('The value of the option %s is refused: %s.', $option, $reason));
    }
}
