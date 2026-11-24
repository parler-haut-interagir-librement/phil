<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Functional\App;

use Override;
use Phil\FormsBundle\Application\CurrentSubject;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member the request names in its `X-Member` header, for the tests.
 */
final readonly class HeaderMember implements CurrentSubject
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->requests->getCurrentRequest()?->headers->get('X-Member');
    }
}
