<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SchedulingPollBundle\Infrastructure\Http\AnswerController;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\AnnounceChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\CloseDuePollsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\EraseRespondentsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\SendChosenDateHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Leaves out the services whose collaborators the application did not enable: the handlers without Messenger's bus,
 * the pages without Twig or the translator. The polls themselves need none of them; the mail goes without the Mailer,
 * and the schedule without its lock, each doing less, as they say.
 */
final readonly class OptionalServicesPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $needs = [
            AnnounceChosenDateHandler::class => ['messenger.default_bus'],
            CloseDuePollsHandler::class => ['messenger.default_bus'],
            EraseRespondentsHandler::class => ['messenger.default_bus'],
            SendChosenDateHandler::class => ['messenger.default_bus'],
            AnswerController::class => ['twig', 'translator'],
        ];

        foreach ($needs as $service => $ids) {
            if (array_any($ids, static fn (string $id): bool => !$container->has($id))) {
                $container->removeDefinition($service);
            }
        }
    }
}
