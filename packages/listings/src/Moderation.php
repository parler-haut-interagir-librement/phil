<?php

declare(strict_types=1);

namespace Phil\Listings;

/**
 * When a kind of listing is moderated: before a listing is shown to anyone, or after, on reports.
 */
enum Moderation: string
{
    /** A submitted listing waits for a moderator's approval; so does a published one its author edits. */
    case BeforePublication = 'before_publication';

    /** A submitted listing is published at once; enough reports hide it until a moderator decides. */
    case AfterPublication = 'after_publication';
}
