<?php

declare(strict_types=1);

namespace Phil\SocialCards;

/**
 * The Open Graph type of a page, `og:type`: each value is the one the protocol defines.
 */
enum ShareType: string
{
    case Website = 'website';

    /** A dated piece of writing: the tags then carry `article:published_time`, `article:modified_time` and `article:author`. */
    case Article = 'article';

    case Profile = 'profile';

    case Book = 'book';

    case Video = 'video.other';
}
