<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Schema;

/**
 * The type of a variable, and what a value of it becomes once checked.
 *
 * | Type | Accepts | Becomes |
 * |---|---|---|
 * | `string` | a text, a number or a `Stringable` | a text of valid UTF-8, at most `max_length` characters |
 * | `integer` | an integer, or a text of one | an integer |
 * | `number` | an integer, a float, or a text of one | an integer or a float |
 * | `boolean` | `true` or `false` | the same |
 * | `datetime` | a `DateTimeInterface`, or an ISO 8601 text — `2026-11-18`, `2026-11-18T19:30:00-05:00` | a `DateTimeImmutable` |
 * | `url` | an absolute `http` or `https` address, read as a browser does by `phil/url` | its normalized text |
 * | `email` | an email address, read by `phil/email-address` | its normalized text |
 * | `list` | a list of at most `max_items` values of the type `items` | a list |
 * | `object` | a map of the variables `fields` | a map |
 */
enum VariableType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Number = 'number';
    case Boolean = 'boolean';
    case Datetime = 'datetime';
    case Url = 'url';
    case Email = 'email';
    case List = 'list';
    case Object = 'object';

    public function isComposite(): bool
    {
        return self::List === $this || self::Object === $this;
    }
}
