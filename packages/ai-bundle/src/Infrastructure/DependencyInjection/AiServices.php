<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\DependencyInjection;

use Phil\AiBundle\Application\AiNotice;
use Phil\AiBundle\Application\AiTasks;
use Phil\AiBundle\Application\AiUsageRegister;
use Phil\AiBundle\Application\KeyPolicy;
use Phil\AiBundle\Application\NoUserKeys;
use Phil\AiBundle\Application\Port\Completions;
use Phil\AiBundle\Application\Port\UserKeys;
use Phil\AiBundle\Application\PriceList;
use Phil\AiBundle\Application\PromptTemplates;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Application\TaskCatalog;
use Phil\AiBundle\Application\TaskDefinition;
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Price;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy;
use Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory;
use Phil\AiBundle\Infrastructure\SymfonyAi\SymfonyAiCompletions;
use Phil\AiBundle\Infrastructure\UserSecrets\UserSecretsKeys;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The bundle's services: the task catalog, the prompts, the price list, the journal, the models through Symfony AI,
 * the person's keys, the quota, `AiTasks`, the notices and the usage register; the journal's module of
 * `phil/privacy-bundle` when it is installed, and the Replicate image platform factory when an HTTP client is.
 */
final class AiServices
{
    private const string EXPORTER_TAG = 'phil_privacy.exporter';

    private const string ERASER_TAG = 'phil_privacy.eraser';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $config
     * @param bool                    $userSecrets whether `phil/user-secrets-bundle` is registered
     */
    public static function register(ContainerBuilder $container, array $config, bool $userSecrets): void
    {
        $platforms = self::map($config['platforms']);

        $container->register(TaskCatalog::class, TaskCatalog::class)
            ->setFactory([self::class, 'catalog'])
            ->setArguments([$config['tasks']]);
        $container->register(PriceList::class, PriceList::class)
            ->setFactory([self::class, 'prices'])
            ->setArguments([$config['currency'], $config['prices']]);
        $container->register(PromptTemplates::class, PromptTemplates::class)
            ->setArguments([new Reference('translator'), $config['translation_domain']]);

        if ('memory' === $config['store']) {
            $container->register(InMemoryJournalRepository::class, InMemoryJournalRepository::class);
            $container->setAlias(JournalRepository::class, InMemoryJournalRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineJournalRepository::class, DoctrineJournalRepository::class)
                ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', self::text($config['entity_manager'])))]);
            $container->setAlias(JournalRepository::class, DoctrineJournalRepository::class)->setPublic(true);
        }

        $container->register(SymfonyAiCompletions::class, SymfonyAiCompletions::class)
            ->setArguments([
                array_map(static fn (array $platform): Reference => new Reference(self::text($platform['platform'])), $platforms),
                array_map(static fn (array $platform): Reference => new Reference(self::text($platform['user_key_factory'])), array_filter($platforms, static fn (array $platform): bool => null !== $platform['user_key_factory'])),
            ]);
        $container->setAlias(Completions::class, SymfonyAiCompletions::class)->setPublic(true);

        if ($userSecrets) {
            $container->register(UserSecretsKeys::class, UserSecretsKeys::class)
                ->setArguments([new Reference(UserSecrets::class), array_map(static fn (array $platform): mixed => $platform['secret'], array_filter($platforms, static fn (array $platform): bool => null !== $platform['secret']))]);
            $container->setAlias(UserKeys::class, UserSecretsKeys::class);
        } else {
            $container->register(NoUserKeys::class, NoUserKeys::class);
            $container->setAlias(UserKeys::class, NoUserKeys::class);
        }

        $quota = null;

        if (null !== $config['usage_meter']) {
            $limits = \is_array($config['quota']) ? $config['quota'] : [];
            $quota = new Reference(Quota::class);
            $concurrency = $limits['concurrency'] ?? null;
            $container->register(Quota::class, Quota::class)
                ->setArguments([new Reference(self::text($config['usage_meter'])), $limits['tokens_meter'], $limits['tasks_meter'], $limits['count_user_keys'], $limits['budget_meter'], $limits['ceiling_meter'], $limits['ceiling_subject'], null === $concurrency ? null : new Reference(self::text($limits['semaphore_factory'])), $concurrency]);
        }

        $container->register(AiTasks::class, AiTasks::class)
            ->setArguments([
                new Reference(TaskCatalog::class),
                new Reference(PromptTemplates::class),
                new Reference(Completions::class),
                new Reference(JournalRepository::class),
                new Reference(PriceList::class),
                new Reference('clock'),
                new Reference(UserKeys::class),
                KeyPolicy::from(self::text($config['key_policy'])),
                $quota,
            ])
            ->setPublic(true);

        $container->register(AiNotice::class, AiNotice::class)
            ->setArguments([new Reference('translator')])
            ->setPublic(true);
        $container->register(AiUsageRegister::class, AiUsageRegister::class)
            ->setArguments([new Reference(TaskCatalog::class)])
            ->setPublic(true);

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(AiJournalPrivacy::class, AiJournalPrivacy::class)
                ->setArguments([new Reference(JournalRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        if (interface_exists(HttpClientInterface::class)) {
            $container->register(ReplicatePlatformFactory::class, ReplicatePlatformFactory::class)
                ->setArguments([new Reference('http_client')]);
        }
    }

    /**
     * The catalog from `phil_ai.tasks`.
     *
     * @param array<string, array{platform: string, model: string, options: array<string, mixed>, reserve: int, output?: string, structure?: class-string|null, tools?: array<string, array{description: string, parameters: array<string, mixed>|null}>, purpose?: string}> $tasks
     */
    public static function catalog(array $tasks): TaskCatalog
    {
        $definitions = [];

        foreach ($tasks as $kind => $task) {
            $tools = [];

            foreach ($task['tools'] ?? [] as $name => $tool) {
                $tools[] = new ToolDefinition($name, $tool['description'], $tool['parameters']);
            }

            $definitions[] = new TaskDefinition(TaskKind::fromString($kind), $task['platform'], $task['model'], $task['options'], $task['reserve'], Output::from($task['output'] ?? Output::Text->value), $task['structure'] ?? null, $tools, $task['purpose'] ?? '');
        }

        return new TaskCatalog(...$definitions);
    }

    /**
     * The price list from `phil_ai.currency` and `phil_ai.prices`.
     *
     * @param array<string, array{input: int, output: int, per_call?: int}> $prices
     */
    public static function prices(string $currency, array $prices): PriceList
    {
        return new PriceList($currency, array_map(static fn (array $price): Price => new Price($price['input'], $price['output'], $currency, $price['per_call'] ?? 0), $prices));
    }

    /**
     * @return array<array-key, array<array-key, mixed>>
     */
    private static function map(mixed $value): array
    {
        return array_filter(\is_array($value) ? $value : [], is_array(...));
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
