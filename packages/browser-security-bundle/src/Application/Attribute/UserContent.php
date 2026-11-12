<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Attribute;

use Attribute;

/**
 * Serves the responses of a controller with the strict headers of user content: on its class or on one method.
 *
 * ```php
 * #[UserContent]
 * public function download(Upload $upload): BinaryFileResponse
 * ```
 *
 * @see \Phil\BrowserSecurityBundle\Application\UserContentPolicy
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class UserContent
{
}
