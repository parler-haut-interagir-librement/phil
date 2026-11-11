<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * What can be wrong with the notes of a document.
 */
enum ProblemKind: string
{
    /** A reference names a note the document does not hold; it is left out of the output. */
    case MissingNote = 'missing-note';

    /** A note no reference names; it is left out of the output. */
    case UnreferencedNote = 'unreferenced-note';

    /** A second note uses the identifier of an earlier one; the first is kept. */
    case DuplicateId = 'duplicate-id';

    /** The text of a note holds a reference; it is left out of the output. */
    case ReferenceInNote = 'reference-in-note';

    /** One note is referenced several times; it is written once, with one backlink per reference. */
    case RepeatedReference = 'repeated-reference';

    /**
     * Whether the document loses something: a repeated reference is legitimate, and only reported.
     */
    public function isError(): bool
    {
        return self::RepeatedReference !== $this;
    }
}
