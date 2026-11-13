<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * The names the application shows for authors; `null` for an author it does not know, shown by the reference.
 */
interface AuthorDirectory
{
    public function name(ResourceReference $author): ?string;
}
