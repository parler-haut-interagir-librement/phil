<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * What went wrong with a call of the data model, whatever the version; `ScormError::for()` gives its number in each.
 */
enum Mistake
{
    /** The element is not one the data model defines. */
    case Undefined;
    /** The element is defined, but this run time does not keep it. */
    case Unimplemented;
    /** The element is defined, but holds no value yet and has no default. */
    case NotInitialized;
    case ReadOnly;
    case WriteOnly;
    /** `_children`, `_count` or `_version` written. */
    case Keyword;
    /** The value is not of the element's type. */
    case TypeMismatch;
    /** The value is of the element's type, outside its range. */
    case OutOfRange;
    /** The value needs another to be set first: the identifier of an interaction before its other elements. */
    case Dependency;
    /** An element of a collection written past the next free index. */
    case OutOfOrder;
    /** An element of a collection read past its count. */
    case GetOutOfRange;
    /** `_children` asked of an element that has none. */
    case NoChildren;
    /** `_count` asked of an element that is not a collection. */
    case NoCount;
    /** Another failure of a write: a collection full, an identifier given twice. */
    case SetFailed;
    /** Another failure of a read. */
    case GetFailed;
}
