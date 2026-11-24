<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

/**
 * What the erasure of a person does to the reviews they wrote: `Anonymise` keeps the scores without the author, title
 * or text; `Remove` deletes the reviews. Either way the ratings of their subjects are computed again.
 */
enum ErasureMode: string
{
    case Anonymise = 'anonymise';
    case Remove = 'remove';
}
