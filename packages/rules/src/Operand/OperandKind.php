<?php

declare(strict_types=1);

namespace Phil\Rules\Operand;

/**
 * What an operand holds, as written in a rule.
 */
enum OperandKind: string
{
    /** A UTF-8 text without control characters, bounded in bytes. */
    case Text = 'text';

    /** A name or a reference: a letter or digit, then letters, digits, `_`, `.`, `:`, `/` or `-`; 128 bytes at most. */
    case Identifier = 'identifier';

    /** An integer, between the bounds of the operand when it has any. */
    case Integer = 'integer';

    /** A finite number, read as a float. */
    case Number = 'number';

    /** `true` or `false`. */
    case Boolean = 'boolean';

    /** One of the texts the operand lists. */
    case Choice = 'choice';

    /** A calendar date written `YYYY-MM-DD`. */
    case Date = 'date';

    /** A text, an integer, a finite number or a boolean. */
    case Scalar = 'scalar';

    /** A non-empty list of distinct identifiers. */
    case IdentifierList = 'identifier-list';

    /** A non-empty list of distinct scalars. */
    case ScalarList = 'scalar-list';
}
