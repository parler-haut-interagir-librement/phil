<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * Why a cell or a row is refused. The value is a stable code an application translates; `message()` is the English
 * sentence the rejected-rows file carries.
 */
enum Reason: string
{
    case Required = 'required';
    case NotAnInteger = 'not-an-integer';
    case NotADecimal = 'not-a-decimal';
    case NotABoolean = 'not-a-boolean';
    case NotADate = 'not-a-date';
    case NotAnEmail = 'not-an-email';
    case TooLong = 'too-long';
    case NotAllowed = 'not-allowed';
    case TooManyCells = 'too-many-cells';
    case MalformedRow = 'malformed-row';
    case DuplicateKey = 'duplicate-key';
    case SelfReference = 'self-reference';
    case UnknownReference = 'unknown-reference';
    case ReferenceCycle = 'reference-cycle';
    case ReferenceRejected = 'reference-rejected';
    case DateOrder = 'date-order';
    case RefusedByTarget = 'refused-by-target';

    public function message(): string
    {
        return match ($this) {
            self::Required => 'is required',
            self::NotAnInteger => 'is not a whole number',
            self::NotADecimal => 'is not a decimal number',
            self::NotABoolean => 'is not yes or no',
            self::NotADate => 'is not a date written YYYY-MM-DD',
            self::NotAnEmail => 'is not an email address',
            self::TooLong => 'is too long',
            self::NotAllowed => 'is not one of the allowed values',
            self::TooManyCells => 'the row has more cells than the header',
            self::MalformedRow => 'the row has text after a closing quote',
            self::DuplicateKey => 'repeats the key of another row',
            self::SelfReference => 'refers to its own row',
            self::UnknownReference => 'refers to nothing known',
            self::ReferenceCycle => 'is part of a cycle of references',
            self::ReferenceRejected => 'refers to a row that is refused',
            self::DateOrder => 'is before its start date',
            self::RefusedByTarget => 'is refused',
        };
    }
}
