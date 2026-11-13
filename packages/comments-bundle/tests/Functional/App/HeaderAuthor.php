<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Functional\App;

use Override;
use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The author the request names in `X-Test-Author`, as `user:<name>`; nobody without it.
 */
final readonly class HeaderAuthor implements AuthorProvider
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function author(): ?ResourceReference
    {
        $name = $this->requests->getCurrentRequest()?->headers->get('X-Test-Author');

        return null === $name ? null : ResourceReference::of('user', $name);
    }
}
