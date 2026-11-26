<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Symfony\Component\Console\Input\InputInterface;

/**
 * `make:ddd:query`: a query in the application layer of a context, and its handler on the query bus.
 *
 * ```console
 * $ bin/console make:ddd:query Catalog FindProduct
 * ```
 *
 * writes `App\Catalog\Application\FindProductQuery` and `App\Catalog\Application\FindProductHandler`.
 */
final class MakeDddQuery extends AbstractDddMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:ddd:query';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a query of a bounded context, and its handler on the query bus';
    }

    #[Override]
    protected function nameExample(): string
    {
        return 'FindProduct';
    }

    #[Override]
    protected function what(): string
    {
        return 'query, with or without its Query suffix';
    }

    #[Override]
    protected function files(DddSkeleton $skeleton, string $context, string $name): array
    {
        return $skeleton->query($context, $name);
    }

    #[Override]
    protected function next(DddSkeleton $skeleton, InputInterface $input): array
    {
        return ['Next: give the query its properties, and replace null by the type of its answer, in the query and in its handler.'];
    }
}
