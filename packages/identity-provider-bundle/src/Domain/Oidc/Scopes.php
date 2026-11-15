<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * A list of values separated by spaces, as `scope` and `prompt` are written (RFC 6749, section 3.3): split, without
 * empty values or repeats, in the order given.
 */
final class Scopes
{
    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function split(?string $value): array
    {
        $values = [];

        foreach (explode(' ', $value ?? '') as $item) {
            if ('' !== $item && !\in_array($item, $values, true)) {
                $values[] = $item;
            }
        }

        return $values;
    }
}
