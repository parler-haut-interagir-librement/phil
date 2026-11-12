<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Markup;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The trusted role whose holders write raw HTML: `phil_content.raw_html_role`, checked by Symfony's authorization
 * checker for the user writing a body. A save run without a signed-in user, from the console or a scheduled task, is
 * not granted it; a publication does not ask, since the body says whether its author was.
 */
final readonly class RawHtmlRole implements RawHtmlPermission
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private string $role,
    ) {
    }

    #[Override]
    public function isGranted(): bool
    {
        return $this->authorization->isGranted($this->role);
    }
}
