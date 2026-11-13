<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * Who the current request comments as, from the application's security — `user:42` —; `null` for nobody, whom the
 * endpoints refuse.
 */
interface AuthorProvider
{
    public function author(): ?ResourceReference;
}
