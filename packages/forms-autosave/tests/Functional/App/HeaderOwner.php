<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Functional\App;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The user the header `X-User` names, as a firewall would.
 */
final readonly class HeaderOwner implements DraftOwner
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->requests->getMainRequest()?->headers->get('X-User');
    }
}
