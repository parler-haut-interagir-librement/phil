<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Application;

use InvalidArgumentException;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\Url\UrlType;

/**
 * The kinds of address a field or a constraint accepts, as the enum sets `UrlTypes` detects among: written as an
 * `EnumSet`, an enum class for all its cases, single cases, or a list of these. Cases of one enum make one set; sets
 * keep the order they first appear in.
 */
final class AcceptedUrlTypes
{
    /**
     * @param mixed $types an `EnumSet`, an enum class, a case, an iterable of these, or `null` for every international kind
     *
     * @return non-empty-list<EnumSet<covariant UrlType>>
     */
    public static function sets(mixed $types): array
    {
        $types ??= KnownUrlType::class;
        $sets = [];

        foreach (is_iterable($types) && !$types instanceof EnumSet ? $types : [$types] as $type) {
            $set = match (true) {
                $type instanceof EnumSet => $type,
                $type instanceof UrlType => EnumSet::of($type::class, $type),
                \is_string($type) && is_subclass_of($type, UrlType::class) => EnumSet::all($type),
                default => throw new InvalidArgumentException(\sprintf('A kind of address is a case of an enum implementing %s, its class, or an enum set of them; %s is not.', UrlType::class, get_debug_type($type))),
            };

            $sets[$set->enum()] = isset($sets[$set->enum()]) ? $sets[$set->enum()]->union($set) : $set;
        }

        return [] === $sets ? throw new InvalidArgumentException('A field accepts at least one kind of address.') : array_values($sets);
    }
}
