<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Application;

use InvalidArgumentException;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\MessengerType;

/**
 * The messaging services a field or a constraint accepts, as the enum sets `Messengers` detects among: written as an
 * `EnumSet`, an enum class for all its cases, single cases, or a list of these. Cases of one enum make one set; sets
 * keep the order they first appear in.
 */
final class AcceptedMessengers
{
    /**
     * @param mixed $types an `EnumSet`, an enum class, a case, an iterable of these, or `null` for every international service
     *
     * @return non-empty-list<EnumSet<covariant MessengerType>>
     */
    public static function sets(mixed $types): array
    {
        $types ??= KnownMessenger::class;
        $sets = [];

        foreach (is_iterable($types) && !$types instanceof EnumSet ? $types : [$types] as $type) {
            $set = match (true) {
                $type instanceof EnumSet => $type,
                $type instanceof MessengerType => EnumSet::of($type::class, $type),
                \is_string($type) && is_subclass_of($type, MessengerType::class) => EnumSet::all($type),
                default => throw new InvalidArgumentException(\sprintf('A messaging service is a case of an enum implementing %s, its class, or an enum set of them; %s is not.', MessengerType::class, get_debug_type($type))),
            };

            $sets[$set->enum()] = isset($sets[$set->enum()]) ? $sets[$set->enum()]->union($set) : $set;
        }

        return [] === $sets ? throw new InvalidArgumentException('A field accepts at least one messaging service.') : array_values($sets);
    }

    /**
     * Every accepted service, set by set in order: the choices a field offers.
     *
     * @param mixed $types as for `sets()`
     *
     * @return list<MessengerType>
     */
    public static function cases(mixed $types): array
    {
        $cases = [];

        foreach (self::sets($types) as $set) {
            foreach ($set as $case) {
                $cases[] = $case;
            }
        }

        return $cases;
    }
}
