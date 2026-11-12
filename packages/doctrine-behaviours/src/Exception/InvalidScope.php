<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use LogicException;

/**
 * A scope names a field it cannot compare, or two records are compared across scopes: a group of positions or the
 * scope of a slug is made of the record's own columns and of to-one relations held by one column.
 */
final class InvalidScope extends LogicException
{
    public static function unknownField(string $class, string $field): self
    {
        return new self(\sprintf('The scope of %s names "%s", which is neither a column nor a to-one relation it holds by one column.', $class, $field));
    }

    public static function compositeTarget(string $class, string $field): self
    {
        return new self(\sprintf('The scope of %s names "%s", a relation to a record whose identifier is itself a relation; name a column instead.', $class, $field));
    }

    public static function translationWithoutLocale(string $class): self
    {
        return new self(\sprintf('The slug of the translation %s is unique within a scope without its locale: name "locale" in slugScope(), so that each locale has its own slugs.', $class));
    }

    public static function notStored(string $class): self
    {
        return new self(\sprintf('A record of %s is moved before it or its group is stored: flush it first.', $class));
    }

    public static function otherGroup(string $class): self
    {
        return new self(\sprintf('Two records of %s are placed against each other across groups or tables: a record moves within its own group.', $class));
    }
}
