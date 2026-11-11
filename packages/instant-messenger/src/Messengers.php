<?php

declare(strict_types=1);

namespace Phil\InstantMessenger;

use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Exception\InvalidHandle;

/**
 * Which service a typed text is a handle on, among the services a field accepts: the first case, set by set and in
 * each enum's declaration order, that reads it.
 *
 * Handles collide — a user name may be a Telegram, a Line or a Discord one —: detection is for links, which name
 * their service, and for fields whose services write their handles apart. Elsewhere, ask the person for the service.
 */
final class Messengers
{
    /**
     * @param EnumSet<covariant MessengerType> ...$accepted
     */
    public static function detect(string $input, EnumSet ...$accepted): ?Handle
    {
        foreach ($accepted as $set) {
            foreach ($set as $type) {
                $handle = $type->normalize($input);

                // Read once: the canonical handle the service gave is the handle.
                if (null !== $handle) {
                    return Handle::fromCanonical($type, $handle);
                }
            }
        }

        return null;
    }

    /**
     * @param EnumSet<covariant MessengerType> ...$accepted
     */
    public static function handle(string $input, EnumSet ...$accepted): Handle
    {
        return self::detect($input, ...$accepted) ?? throw InvalidHandle::noType($input);
    }
}
