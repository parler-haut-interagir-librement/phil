<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The stable identifier of a person the core keeps — a customer, a wish list's owner, an employee who forces a
 * transition or grants a discount —, as the application names them: 1 to 255 visible ASCII characters without `@`,
 * so that an email address or a username never lands in an order, a journal or an event.
 */
final readonly class Identifier
{
    private function __construct()
    {
    }

    /**
     * @param string $what what the identifier names, for the message: "customer", "actor"
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match('/^[\x21-\x3F\x41-\x7E]{1,255}$/D', $value)) {
            throw InvalidValue::because(\sprintf('"%s" is not the stable identifier of a %s: 1 to 255 visible ASCII characters without "@", never an email address.', $value, $what));
        }

        return $value;
    }
}
