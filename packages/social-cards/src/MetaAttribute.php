<?php

declare(strict_types=1);

namespace Phil\SocialCards;

/**
 * How a tag names what it carries.
 */
enum MetaAttribute: string
{
    /** `<meta property>`, the attribute of Open Graph and of its `article:` tags. */
    case Property = 'property';

    /** `<meta name>`, the attribute of X, of the fediverse and of the description. */
    case Name = 'name';

    /** `<link rel>`, whose address is its `href`. */
    case Link = 'rel';
}
