<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

/**
 * Why a reader did not keep part of its source.
 */
enum LossReason: string
{
    /** An element UJO has no place for, dropped with its content: a script, a form, an object. */
    case UnknownElement = 'unknown-element';

    /** A frame whose source is no service of the `embed` block: its link is kept alone. */
    case UnknownEmbed = 'unknown-embed';

    /** A link or a source whose scheme is not allowed: its text is kept alone. */
    case RefusedScheme = 'refused-scheme';

    /** A link to an anchor no element of the page names: its text is kept alone. */
    case UnknownAnchor = 'unknown-anchor';

    /** Elements nested deeper than UJO allows: their text is kept alone. */
    case Depth = 'depth';

    /** An inline SVG drawing read without a store of extracted elements. */
    case SvgWithoutStore = 'svg-without-store';

    /** An inline SVG drawing the SVG sanitizer refused whole, or the store did not take. */
    case RefusedSvg = 'refused-svg';

    /** A video or a sound without any source UJO accepts. */
    case MissingSource = 'missing-source';

    /** `data-*` attributes of an inline element, which UJO keeps on blocks only. */
    case DataAttribute = 'data-attribute';

    /** A comment no block could take as its note. */
    case Comment = 'comment';

    /** A checkbox in a list item that is not at its start, where a checklist puts it: the box and its state are dropped. */
    case MisplacedCheckbox = 'misplaced-checkbox';

    /** A checkbox at the start of an item, in a list some items of which say no state: the list is not a checklist. */
    case PartialChecklist = 'partial-checklist';

    /** A table in a list item, which UJO has no place for: the table is dropped, the rest of the item kept. */
    case TableInListItem = 'table-in-list-item';
}
