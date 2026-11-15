<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Security;

use LogicException;
use ReflectionMethod;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What a public method of the user class gives, as text: how a stable identifier is read from whatever user class an
 * application has.
 *
 * @internal
 */
final class UserMethod
{
    /**
     * @param string $role what the method is for, in the errors: "The blame names", "The owner is named"
     */
    public static function read(UserInterface $user, string $method, string $role): ?string
    {
        $reflection = method_exists($user, $method) ? new ReflectionMethod($user, $method) : null;

        if (!$reflection instanceof ReflectionMethod || !$reflection->isPublic() || $reflection->isStatic() || $reflection->getNumberOfRequiredParameters() > 0) {
            throw new LogicException(\sprintf('%s a user by %s::%s(), which does not exist as a public method without argument.', $role, $user::class, $method));
        }

        $name = $reflection->invoke($user);

        return match (true) {
            null === $name => null,
            \is_string($name), \is_int($name), $name instanceof Stringable => (string) $name,
            default => throw new LogicException(\sprintf('%s a user by %s::%s(), which gives no string, number or Stringable.', $role, $user::class, $method)),
        };
    }
}
