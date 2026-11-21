<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Application\Training\QuebecTrainingReport;
use Phil\ReportingBundle\Application\Training\TrainingHeadings;
use Phil\ReportingBundle\Application\Training\TrainingHoursReport;
use Phil\ReportingBundle\Application\Training\TrainingLedger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The training reports left out of an application that has no `TrainingSource`: there is nothing to total.
 */
final class TrainingReportsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(TrainingSource::class)) {
            return;
        }

        foreach ([TrainingHoursReport::class, QuebecTrainingReport::class, TrainingLedger::class, TrainingHeadings::class] as $id) {
            $container->removeDefinition($id);
        }
    }
}
