<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Override;
use Phil\ContentBundle\Domain\Routing\RouteId;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * The identifier of a route: a native `uuid` column where the platform has one, `BINARY(16)` elsewhere.
 *
 * @extends IdentifierType<RouteId>
 */
final class RouteIdType extends IdentifierType
{
    public const string NAME = 'phil_content_route_id';

    #[Override]
    protected function identifierClass(): string
    {
        return RouteId::class;
    }
}
