<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\AudioBundle\Application\AudioJobs;
use Phil\AudioBundle\Application\Handler\ComputeWaveformHandler;
use Phil\AudioBundle\Application\Handler\TranscodeAudioHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the jobs when the application has no message bus: Messenger installed but not enabled.
 */
final readonly class JobsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('messenger.default_bus')) {
            return;
        }

        foreach ([AudioJobs::class, TranscodeAudioHandler::class, ComputeWaveformHandler::class] as $id) {
            $container->removeDefinition($id);
        }
    }
}
