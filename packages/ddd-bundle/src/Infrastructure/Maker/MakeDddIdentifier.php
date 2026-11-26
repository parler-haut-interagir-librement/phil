<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Symfony\Component\Console\Input\InputInterface;

/**
 * `make:ddd:identifier`: an identifier in the domain of a context, and its DBAL type in the infrastructure.
 *
 * ```console
 * $ bin/console make:ddd:identifier Catalog Product
 * ```
 *
 * writes `App\Catalog\Domain\ProductId` and `App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType`, and
 * shows the line that registers the type with DoctrineBundle.
 */
final class MakeDddIdentifier extends AbstractDddMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:ddd:identifier';
    }

    public static function getCommandDescription(): string
    {
        return 'Create an identifier of a bounded context, and its Doctrine DBAL type';
    }

    #[Override]
    protected function nameExample(): string
    {
        return 'Product';
    }

    #[Override]
    protected function what(): string
    {
        return 'identifier, with or without its Id suffix';
    }

    #[Override]
    protected function files(DddSkeleton $skeleton, string $context, string $name): array
    {
        return $skeleton->identifier($context, $name);
    }

    #[Override]
    protected function next(DddSkeleton $skeleton, InputInterface $input): array
    {
        $name = $this->argument($input, 'name');

        return [
            'Next: register the type in config/packages/doctrine.php, under doctrine.dbal.types:',
            '',
            \sprintf("    '%s' => \\%s::class,", $skeleton->typeName($name), $skeleton->typeClass($this->argument($input, 'context'), $name)),
            '',
            \sprintf("then map a column with #[ORM\\Column(type: '%s')].", $skeleton->typeName($name)),
        ];
    }
}
