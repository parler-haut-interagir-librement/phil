<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Symfony\Component\Console\Input\InputInterface;

/**
 * `make:ddd:context`: the three layers of a new bounded context, and its README.
 *
 * ```console
 * $ bin/console make:ddd:context Catalog
 * ```
 */
final class MakeDddContext extends AbstractDddMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:ddd:context';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a bounded context: src/<Context>/{Domain,Application,Infrastructure} and a README';
    }

    #[Override]
    protected function nameExample(): null
    {
        return null;
    }

    #[Override]
    protected function what(): string
    {
        return 'context';
    }

    #[Override]
    protected function files(DddSkeleton $skeleton, string $context, string $name): array
    {
        return $skeleton->context($context);
    }

    #[Override]
    protected function next(DddSkeleton $skeleton, InputInterface $input): array
    {
        $context = $this->argument($input, 'context');

        return [
            \sprintf('Next: describe the context in src/%s/README.md, then, when it has Doctrine entities, add its', $context),
            \sprintf('mapping on src/%s/Infrastructure/Persistence/Doctrine/Entity in config/packages/doctrine.php,', $context),
            \sprintf('and add "%s" to the contexts of the Deptrac rules.', $context),
        ];
    }
}
