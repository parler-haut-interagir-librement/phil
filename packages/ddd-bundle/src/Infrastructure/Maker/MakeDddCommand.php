<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Symfony\Component\Console\Input\InputInterface;

/**
 * `make:ddd:command`: a command in the application layer of a context, and its handler on the command bus.
 *
 * ```console
 * $ bin/console make:ddd:command Catalog AddProduct
 * ```
 *
 * writes `App\Catalog\Application\AddProductCommand` and `App\Catalog\Application\AddProductHandler`.
 */
final class MakeDddCommand extends AbstractDddMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:ddd:command';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a command of a bounded context, and its handler on the command bus';
    }

    #[Override]
    protected function nameExample(): string
    {
        return 'AddProduct';
    }

    #[Override]
    protected function what(): string
    {
        return 'command, with or without its Command suffix';
    }

    #[Override]
    protected function files(DddSkeleton $skeleton, string $context, string $name): array
    {
        return $skeleton->command($context, $name);
    }

    #[Override]
    protected function next(DddSkeleton $skeleton, InputInterface $input): array
    {
        return ['Next: give the command its properties — identifiers and scalars —, and the handler its work, through the ports of the domain.'];
    }
}
