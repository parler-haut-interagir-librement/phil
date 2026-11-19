<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\SuspendBlame;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\SuspendBlameWithExport;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Decorates every eraser of phil/privacy-bundle, the application's and other bundles' alike, so that the blame is
 * suspended while it runs: a flush of an eraser writes no name in `created_by` or `updated_by`. An eraser that also
 * exports keeps doing so through its decorator. Nothing is decorated when the suspension is off.
 */
final class SuspendBlameDuringErasurePass implements CompilerPassInterface
{
    public const string SUFFIX = '.phil_suspend_blame';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BlameSuspension::class)) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds(BehaviourServices::ERASER_TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);
            $exports = \is_string($class) && is_a($class, PersonalDataExporter::class, true);

            $container->register($id.self::SUFFIX, $exports ? SuspendBlameWithExport::class : SuspendBlame::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference($id.self::SUFFIX.'.inner'), new Reference(BlameSuspension::class)]);
        }
    }
}
