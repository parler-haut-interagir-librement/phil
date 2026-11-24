<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection;

use Phil\VirtualClassroom\Exception\InvalidValue;
use SensitiveParameter;

/**
 * The secret shared with a BigBlueButton or Jitsi server, checked when the service of the server is built, from the
 * value the environment gives at that moment: one that begins with "change-me" or "changeme", in any case, is a
 * placeholder copied from an example, known to everyone, and is refused. The server checks the rest: its length, and
 * that it is the one it shares.
 */
final class ServerSecret
{
    private const string PLACEHOLDER = '/^change-?me/i';

    /**
     * @throws InvalidValue when the secret is a placeholder
     */
    public static function check(#[SensitiveParameter] string $secret): string
    {
        if (1 === preg_match(self::PLACEHOLDER, $secret)) {
            throw InvalidValue::because('The secret shared with the server is a placeholder: give it the secret the server shares.');
        }

        return $secret;
    }
}
