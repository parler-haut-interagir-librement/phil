<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\DependencyInjection;

use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Application\DefaultPollPolicy;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Application\PollViews;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\PollBundle\Infrastructure\Twig\PollTwig;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the stores, the policy the configuration names or the default one, the counting methods —
 * the library's four, the single transferable vote of the bundle and every service of the application implementing
 * `CountingMethod` —, the polls, with the cache of their counts when the configuration names one, their views for a
 * page, and the functions of the templates when Twig is there.
 */
final class PollServices
{
    public const string COUNTING_METHOD_TAG = 'phil_poll.counting_method';

    /**
     * @param array{store: string, entity_manager: string, policy: ?string, results_cache: array{pool: ?string, lifetime: int}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryPollRepository::class, InMemoryPollRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->register(InMemoryVoteRepository::class, InMemoryVoteRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(PollRepository::class, InMemoryPollRepository::class)->setPublic(true);
            $container->setAlias(VoteRepository::class, InMemoryVoteRepository::class)->setPublic(true);
        } else {
            $entityManager = new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager');
            $container->register(DoctrinePollRepository::class, DoctrinePollRepository::class)->setArguments([$entityManager]);
            $container->register(DoctrineVoteRepository::class, DoctrineVoteRepository::class)->setArguments([$entityManager]);
            $container->setAlias(PollRepository::class, DoctrinePollRepository::class)->setPublic(true);
            $container->setAlias(VoteRepository::class, DoctrineVoteRepository::class)->setPublic(true);
        }

        if (null === $config['policy']) {
            $container->register(DefaultPollPolicy::class, DefaultPollPolicy::class);
            $container->setAlias(PollPolicy::class, DefaultPollPolicy::class);
        } else {
            $container->setAlias(PollPolicy::class, $config['policy']);
        }

        $container->registerForAutoconfiguration(CountingMethod::class)->addTag(self::COUNTING_METHOD_TAG);

        foreach ([ChoiceCount::class, InstantRunoff::class, Borda::class, Schulze::class, MeekStv::class] as $method) {
            $container->register($method, $method)->addTag(self::COUNTING_METHOD_TAG);
        }

        $container->register(CountingMethods::class, CountingMethods::class)
            ->setArguments([new TaggedIteratorArgument(self::COUNTING_METHOD_TAG)])
            ->setPublic(true);
        $container->register(Polls::class, Polls::class)
            ->setArguments([
                new Reference(PollRepository::class),
                new Reference(VoteRepository::class),
                new Reference('clock'),
                new Reference(PollPolicy::class),
                new Reference(CountingMethods::class),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                null === $config['results_cache']['pool'] ? null : new Reference($config['results_cache']['pool']),
                $config['results_cache']['lifetime'],
            ])
            ->setPublic(true);
        $container->register(PollViews::class, PollViews::class)
            ->setArguments([new Reference(Polls::class), new Reference(PollPolicy::class), new Reference(CountingMethods::class), new Reference('clock')])
            ->setPublic(true);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(PollTwig::class, PollTwig::class)
                ->setArguments([new Reference(PollViews::class)])
                ->setAutoconfigured(true);
        }
    }
}
