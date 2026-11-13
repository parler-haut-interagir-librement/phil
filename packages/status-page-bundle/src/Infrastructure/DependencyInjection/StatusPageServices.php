<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\DependencyInjection;

use LogicException;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Phil\StatusPageBundle\Application\RecordsReader;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\SnapshotPush;
use Phil\StatusPageBundle\Application\StatusPageBuilder;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Infrastructure\Console\PushCommand;
use Phil\StatusPageBundle\Infrastructure\Http\StatusPageController;
use Phil\StatusPageBundle\Infrastructure\Messenger\PushStatusSnapshotHandler;
use Phil\StatusPageBundle\Infrastructure\Publisher\DirectoryPublisher;
use Phil\StatusPageBundle\Infrastructure\Publisher\FlysystemPublisher;
use Phil\StatusPageBundle\Infrastructure\Records\ConfiguredRecords;
use Phil\StatusPageBundle\Infrastructure\Records\JsonFileRecords;
use Phil\StatusPageBundle\Infrastructure\Scheduler\StatusPageSchedule;
use Phil\StatusPageBundle\Infrastructure\Source\DeclaredStatuses;
use Phil\StatusPageBundle\Infrastructure\Source\MonitoringStatuses;
use Phil\StatusPageBundle\Infrastructure\Twig\KitComponents;
use Phil\StatusPageBundle\Infrastructure\Twig\StatusPageExtension;
use Phil\StatusPageBundle\Infrastructure\Twig\TwigPageRenderer;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Schedule;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Target array{type: 'directory'|'flysystem'|'service', path: ?string, storage: ?string, prefix: string, visibility: ?string, service: ?string}
 * @phpstan-type ComponentConfig array{name: string, description: ?string, group: ?string, status: string, checks: list<string>, tasks: list<string>}
 * @phpstan-type Config array{
 *     title: string,
 *     source: 'auto'|'monitoring'|'declared',
 *     source_service: ?string,
 *     clock: string,
 *     history_days: int,
 *     cache_max_age: int,
 *     layout: string,
 *     kit_subdirectory: ?string,
 *     monitoring: array{cache_ttl: int, cache_pool: string},
 *     components: array<string, ComponentConfig>,
 *     records: array{service: ?string, file: ?string, incidents: mixed, maintenances: mixed},
 *     snapshot: array{base_url: ?string, stylesheet: ?string, schedule: ?string, targets: array<string, Target>},
 * }
 */
final readonly class StatusPageServices
{
    /** The source the configuration asks for, read by MonitoringPass. */
    public const string SOURCE_PARAMETER = '.phil_status_page.source';

    /** The prefix of the id of each target's publisher, followed by the target's name. */
    public const string PUBLISHER_PREFIX = 'phil_status_page.publisher.';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->setParameter(self::SOURCE_PARAMETER, $config['source']);
        $container->register(RecordsReader::class, RecordsReader::class);
        self::statuses($container, $config);
        self::records($container, $config['records']);

        $components = [];

        foreach ($config['components'] as $id => $component) {
            $components[] = new Definition(Component::class, [$id, $component['name'], $component['description'], $component['group']]);
        }

        $container->register(StatusPageBuilder::class, StatusPageBuilder::class)
            ->setArguments([
                $config['title'],
                $components,
                new Reference(ComponentStatuses::class),
                new Reference(StatusRecords::class),
                new Reference($config['clock']),
                $config['history_days'],
            ])
            ->setPublic(true);
        $container->register(JsonDocument::class, JsonDocument::class);
        $container->register(AtomFeed::class, AtomFeed::class);

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(StatusPageExtension::class, StatusPageExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(TwigPageRenderer::class, TwigPageRenderer::class)
            ->setArguments([new Reference('twig'), $config['layout'], $config['snapshot']['stylesheet']]);
        $container->setAlias(PageRenderer::class, TwigPageRenderer::class);

        $container->register(StatusPageController::class, StatusPageController::class)
            ->setArguments([
                new Reference(StatusPageBuilder::class),
                new Reference(PageRenderer::class),
                new Reference(JsonDocument::class),
                new Reference(AtomFeed::class),
                new Reference('router'),
                $config['cache_max_age'],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        self::snapshot($container, $config['snapshot']);
    }

    /**
     * @param Config $config
     */
    private static function statuses(ContainerBuilder $container, array $config): void
    {
        $container->register(DeclaredStatuses::class, DeclaredStatuses::class)
            ->setFactory([DeclaredStatuses::class, 'fromValues'])
            ->setArguments([array_map(static fn (array $component): string => $component['status'], $config['components'])]);

        if (null !== $config['source_service']) {
            $container->setAlias(ComponentStatuses::class, $config['source_service'])->setPublic(true);

            return;
        }

        $container->setAlias(ComponentStatuses::class, DeclaredStatuses::class)->setPublic(true);

        if ('declared' === $config['source']) {
            return;
        }

        if (!interface_exists(MonitoringStatus::class)) {
            if ('monitoring' === $config['source']) {
                throw new LogicException('phil_status_page.source "monitoring" reads phil/monitoring-bundle: install it, or choose "declared".');
            }

            return;
        }

        // Aliased as the source by MonitoringPass once the monitoring service is known to exist.
        $container->register(MonitoringStatuses::class, MonitoringStatuses::class)->setArguments([
            new Reference(MonitoringStatus::class),
            new Reference(DeclaredStatuses::class),
            array_map(static fn (array $component): array => $component['checks'], $config['components']),
            array_map(static fn (array $component): array => $component['tasks'], $config['components']),
            new Reference($config['monitoring']['cache_pool'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['monitoring']['cache_ttl'],
        ]);
    }

    /**
     * @param array{service: ?string, file: ?string, incidents: mixed, maintenances: mixed} $records
     */
    private static function records(ContainerBuilder $container, array $records): void
    {
        if (null !== $records['service']) {
            $container->setAlias(StatusRecords::class, $records['service'])->setPublic(true);

            return;
        }

        if (null !== $records['file']) {
            $container->register(JsonFileRecords::class, JsonFileRecords::class)
                ->setArguments([$records['file'], new Reference(RecordsReader::class)]);
            $container->setAlias(StatusRecords::class, JsonFileRecords::class)->setPublic(true);

            return;
        }

        $data = ['incidents' => $records['incidents'], 'maintenances' => $records['maintenances']];

        try {
            new RecordsReader()->read($data);
        } catch (InvalidStatusRecord $invalidStatusRecord) {
            throw new InvalidConfigurationException('phil_status_page.records: '.$invalidStatusRecord->getMessage(), $invalidStatusRecord->getCode(), previous: $invalidStatusRecord);
        }

        $container->register(ConfiguredRecords::class, ConfiguredRecords::class)
            ->setArguments([$data, new Reference(RecordsReader::class)]);
        $container->setAlias(StatusRecords::class, ConfiguredRecords::class)->setPublic(true);
    }

    /**
     * @param array{base_url: ?string, stylesheet: ?string, schedule: ?string, targets: array<string, Target>} $snapshot
     */
    private static function snapshot(ContainerBuilder $container, array $snapshot): void
    {
        if ([] === $snapshot['targets'] || null === $snapshot['base_url']) {
            return;
        }

        $publishers = [];

        foreach ($snapshot['targets'] as $name => $target) {
            $id = self::PUBLISHER_PREFIX.$name;
            $publishers[$name] = new Reference($id);

            match ($target['type']) {
                'directory' => $container->register($id, DirectoryPublisher::class)->setArguments([$target['path']]),
                'flysystem' => $container->register($id, FlysystemPublisher::class)->setArguments([new Reference((string) $target['storage']), $target['prefix'], $target['visibility']]),
                'service' => $container->setAlias($id, (string) $target['service']),
            };
        }

        $container->register(SnapshotPush::class, SnapshotPush::class)
            ->setArguments([
                new Reference(StatusPageBuilder::class),
                new Reference(PageRenderer::class),
                new Reference(JsonDocument::class),
                new Reference(AtomFeed::class),
                new Definition(Url::class, [$snapshot['base_url']])->setFactory([Url::class, 'fromString']),
                $publishers,
                new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(PushCommand::class, PushCommand::class)
                ->setArguments([new Reference(SnapshotPush::class)])
                ->addTag('console.command');
        }

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(PushStatusSnapshotHandler::class, PushStatusSnapshotHandler::class)
                ->setArguments([new Reference(SnapshotPush::class)])
                ->addTag('messenger.message_handler', ['handles' => PushStatusSnapshot::class]);
        }

        if (null !== $snapshot['schedule'] && class_exists(Schedule::class)) {
            $container->register(StatusPageSchedule::class, StatusPageSchedule::class)
                ->setArguments([$snapshot['schedule']])
                ->addTag('scheduler.schedule_provider', ['name' => StatusPageSchedule::NAME]);
        }
    }
}
