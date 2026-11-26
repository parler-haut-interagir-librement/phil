<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Symfony\Component\Console\Input\InputInterface;

/**
 * `make:ddd:event`: a domain event, in the `Event` folder of the domain of a context.
 *
 * ```console
 * $ bin/console make:ddd:event Catalog ProductAdded
 * ```
 */
final class MakeDddEvent extends AbstractDddMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:ddd:event';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a domain event of a bounded context';
    }

    #[Override]
    protected function nameExample(): string
    {
        return 'ProductAdded';
    }

    #[Override]
    protected function what(): string
    {
        return 'event, a fact in the past tense';
    }

    #[Override]
    protected function files(DddSkeleton $skeleton, string $context, string $name): array
    {
        return $skeleton->event($context, $name);
    }

    #[Override]
    protected function next(DddSkeleton $skeleton, InputInterface $input): array
    {
        return ['Next: give the event the identifiers of the fact, and record it where the domain changes.'];
    }
}
