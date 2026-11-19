<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Attribute;

use Attribute;

/**
 * A controller, or one of its actions, that only a token holding the scope reaches. It repeats: every scope named is
 * required. A token without it is answered `403 Forbidden` with `WWW-Authenticate: Bearer error="insufficient_scope"`.
 *
 * ```php
 * #[RequiresScope('projects:read')]
 * final class ProjectController
 * {
 *     #[RequiresScope('projects:write')]
 *     public function update(): Response
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequiresScope
{
    public function __construct(
        public string $scope,
    ) {
    }
}
