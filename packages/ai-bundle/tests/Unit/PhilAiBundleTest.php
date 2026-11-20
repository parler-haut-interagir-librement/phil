<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
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
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\AiBundle\Infrastructure\DependencyInjection\AiServices;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy;
use Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory;
use Phil\AiBundle\Infrastructure\SymfonyAi\SymfonyAiCompletions;
use Phil\AiBundle\Infrastructure\UserSecrets\UserSecretsKeys;
use Phil\AiBundle\PhilAiBundle;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\PhilUserSecretsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAiBundle::class)]
#[CoversClass(AiServices::class)]
final class PhilAiBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_ai', $this->extension()->getAlias());
        self::assertSame(['service_container', TaskCatalog::class, PriceList::class, PromptTemplates::class, DoctrineJournalRepository::class, SymfonyAiCompletions::class, NoUserKeys::class, AiTasks::class, AiNotice::class, AiUsageRegister::class, AiJournalPrivacy::class, ReplicatePlatformFactory::class], array_keys($container->getDefinitions()));
        self::assertSame([AiServices::class, 'catalog'], $container->getDefinition(TaskCatalog::class)->getFactory());
        self::assertSame([['draft.rewrite' => ['platform' => 'main', 'model' => 'model-a', 'options' => [], 'reserve' => 1, 'output' => 'text', 'structure' => null, 'tools' => [], 'purpose' => '']]], $container->getDefinition(TaskCatalog::class)->getArguments());
        self::assertSame([AiServices::class, 'prices'], $container->getDefinition(PriceList::class)->getFactory());
        self::assertSame(['USD', []], $container->getDefinition(PriceList::class)->getArguments());
        self::assertEquals([new Reference('translator'), 'phil_ai'], $container->getDefinition(PromptTemplates::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineJournalRepository::class)->getArguments());
        self::assertSame([DoctrineJournalRepository::class, true], [(string) $container->getAlias(JournalRepository::class), $container->getAlias(JournalRepository::class)->isPublic()]);
        self::assertEquals([['main' => new Reference('ai.platform.main')], []], $container->getDefinition(SymfonyAiCompletions::class)->getArguments());
        self::assertSame([SymfonyAiCompletions::class, true], [(string) $container->getAlias(Completions::class), $container->getAlias(Completions::class)->isPublic()]);
        self::assertSame([NoUserKeys::class, false], [(string) $container->getAlias(UserKeys::class), $container->getAlias(UserKeys::class)->isPublic()]);
        self::assertEquals([
            new Reference(TaskCatalog::class), new Reference(PromptTemplates::class), new Reference(Completions::class), new Reference(JournalRepository::class),
            new Reference(PriceList::class), new Reference('clock'), new Reference(UserKeys::class), KeyPolicy::PreferUser, null,
        ], $container->getDefinition(AiTasks::class)->getArguments());
        self::assertTrue($container->getDefinition(AiTasks::class)->isPublic());
        self::assertFalse($container->getDefinition(TaskCatalog::class)->isPublic());
        self::assertEquals([new Reference('translator')], $container->getDefinition(AiNotice::class)->getArguments());
        self::assertEquals([new Reference(TaskCatalog::class)], $container->getDefinition(AiUsageRegister::class)->getArguments());
        self::assertSame([true, true], [$container->getDefinition(AiNotice::class)->isPublic(), $container->getDefinition(AiUsageRegister::class)->isPublic()]);
        self::assertEquals([new Reference(JournalRepository::class)], $container->getDefinition(AiJournalPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(AiJournalPrivacy::class)->getTags());
        self::assertEquals([new Reference('http_client')], $container->getDefinition(ReplicatePlatformFactory::class)->getArguments());
        self::assertFalse($container->getDefinition(ReplicatePlatformFactory::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $builder = new ContainerBuilder();
        $builder->registerExtension(new PhilUserSecretsBundle()->getContainerExtension() ?? self::fail('The secrets bundle has no extension.'));
        $builder->registerExtension($extension);

        $extension->prepend($builder);

        $container = $this->load([
            'platforms' => [
                'main' => ['platform' => 'ai.platform.main', 'user_key_factory' => 'app.main_factory', 'secret' => 'main-key'],
                'eu-2' => ['platform' => 'ai.platform.eu'],
                'other' => ['platform' => 'ai.platform.other', 'user_key_factory' => 'app.other_factory'],
            ],
            'currency' => 'CAD',
            'prices' => ['model-a' => ['input' => 3, 'output' => 15], 'zero' => ['input' => 1, 'output' => 1, 'per_call' => 0], 'free-model' => ['input' => 0, 'output' => 0, 'per_call' => 40000]],
            'tasks' => [
                'draft.rewrite' => ['platform' => 'eu-2', 'model' => 'model-a', 'options' => ['max_tokens' => 64, 'stop' => ['END']], 'reserve' => 900, 'tools' => ['look-up' => ['description' => 'Looks a rule up.', 'parameters' => ['type' => 'object']]], 'purpose' => 'Rewrites a passage.'],
                'name-invent' => ['platform' => 'main', 'model' => 'free-model', 'options' => ['top-k' => 5], 'reserve' => 1, 'output' => 'structured', 'structure' => 'App\\Name'],
            ],
            'key_policy' => 'require_user',
            'usage_meter' => 'app.usage_meter',
            'quota' => ['tokens_meter' => 'ai_tokens', 'tasks_meter' => 'ai_tasks', 'count_user_keys' => true, 'budget_meter' => 'ai_budget', 'ceiling_meter' => 'ai_ceiling', 'ceiling_subject' => 'site'],
            'translation_domain' => 'prompts',
            'store' => 'memory',
        ], $extension);

        self::assertSame([[
            'draft.rewrite' => ['platform' => 'eu-2', 'model' => 'model-a', 'options' => ['max_tokens' => 64, 'stop' => ['END']], 'reserve' => 900, 'tools' => ['look-up' => ['description' => 'Looks a rule up.', 'parameters' => ['type' => 'object']]], 'purpose' => 'Rewrites a passage.', 'output' => 'text', 'structure' => null],
            'name-invent' => ['platform' => 'main', 'model' => 'free-model', 'options' => ['top-k' => 5], 'reserve' => 1, 'output' => 'structured', 'structure' => 'App\\Name', 'tools' => [], 'purpose' => ''],
        ]], $container->getDefinition(TaskCatalog::class)->getArguments());
        self::assertSame(['CAD', ['model-a' => ['input' => 3, 'output' => 15, 'per_call' => 0], 'zero' => ['input' => 1, 'output' => 1, 'per_call' => 0], 'free-model' => ['input' => 0, 'output' => 0, 'per_call' => 40000]]], $container->getDefinition(PriceList::class)->getArguments());
        self::assertSame('prompts', $container->getDefinition(PromptTemplates::class)->getArgument(1));
        self::assertTrue($container->hasDefinition(InMemoryJournalRepository::class));
        self::assertFalse($container->hasDefinition(DoctrineJournalRepository::class));
        self::assertSame([InMemoryJournalRepository::class, true], [(string) $container->getAlias(JournalRepository::class), $container->getAlias(JournalRepository::class)->isPublic()]);
        self::assertEquals([
            ['main' => new Reference('ai.platform.main'), 'eu-2' => new Reference('ai.platform.eu'), 'other' => new Reference('ai.platform.other')],
            ['main' => new Reference('app.main_factory'), 'other' => new Reference('app.other_factory')],
        ], $container->getDefinition(SymfonyAiCompletions::class)->getArguments());
        self::assertEquals([new Reference(UserSecrets::class), ['main' => 'main-key']], $container->getDefinition(UserSecretsKeys::class)->getArguments());
        self::assertSame(UserSecretsKeys::class, (string) $container->getAlias(UserKeys::class));
        self::assertFalse($container->hasDefinition(NoUserKeys::class));
        self::assertEquals([new Reference('app.usage_meter'), 'ai_tokens', 'ai_tasks', true, 'ai_budget', 'ai_ceiling', 'site'], $container->getDefinition(Quota::class)->getArguments());
        self::assertSame([KeyPolicy::RequireUser], [$container->getDefinition(AiTasks::class)->getArgument(7)]);
        self::assertEquals(new Reference(Quota::class), $container->getDefinition(AiTasks::class)->getArgument(8));

        self::assertEquals([new Reference('doctrine.orm.journal_entity_manager')], $this->load(['entity_manager' => 'journal'])->getDefinition(DoctrineJournalRepository::class)->getArguments());
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DoctrineJournalRepository::class));
        self::assertEquals([new Reference('app.usage_meter'), null, null, false, null, null, 'phil_ai.application'], $this->load(['usage_meter' => 'app.usage_meter'])->getDefinition(Quota::class)->getArguments());
        self::assertSame(KeyPolicy::Application, $this->load(['key_policy' => 'application'])->getDefinition(AiTasks::class)->getArgument(7));
    }

    public function testTheFactoriesBuildTheCatalogAndThePrices(): void
    {
        $catalog = AiServices::catalog([
            'draft.rewrite' => ['platform' => 'main', 'model' => 'model-a', 'options' => ['max_tokens' => 64], 'reserve' => 900, 'tools' => ['look_up' => ['description' => 'Looks a rule up.', 'parameters' => ['type' => 'object']]], 'purpose' => 'Rewrites a passage.'],
            'title.suggest' => ['platform' => 'main', 'model' => 'model-b', 'options' => [], 'reserve' => 1],
            'cover.draw' => ['platform' => 'main', 'model' => 'model-c', 'options' => [], 'reserve' => 1, 'output' => 'image'],
            'name.invent' => ['platform' => 'main', 'model' => 'model-d', 'options' => [], 'reserve' => 1, 'output' => 'structured', 'structure' => Tokens::class],
        ]);
        $definition = $catalog->get(TaskKind::fromString('draft.rewrite'));

        self::assertSame(['draft.rewrite', 'title.suggest', 'cover.draw', 'name.invent'], $catalog->kinds());
        self::assertSame(['main', 'model-a', ['max_tokens' => 64], 900, Output::Text, null, 'Rewrites a passage.'], [$definition->platform, $definition->model, $definition->options, $definition->reserve, $definition->output, $definition->structure, $definition->purpose]);
        self::assertEquals([new ToolDefinition('look_up', 'Looks a rule up.', ['type' => 'object'])], $definition->tools);
        self::assertSame([Output::Text, [], ''], [$catalog->get(TaskKind::fromString('title.suggest'))->output, $catalog->get(TaskKind::fromString('title.suggest'))->tools, $catalog->get(TaskKind::fromString('title.suggest'))->purpose]);
        self::assertSame(Output::Image, $catalog->get(TaskKind::fromString('cover.draw'))->output);
        self::assertSame([Output::Structured, Tokens::class], [$catalog->get(TaskKind::fromString('name.invent'))->output, $catalog->get(TaskKind::fromString('name.invent'))->structure]);

        $prices = AiServices::prices('CAD', ['model-a' => ['input' => 3_000_000, 'output' => 15_000_000], 'model-c' => ['input' => 0, 'output' => 0, 'per_call' => 40_000]]);
        self::assertSame(['CAD', 111, 0, 40_000], [$prices->currency(), $prices->costOf('model-a', new Tokens(12, 5))->micros, $prices->costOf('model-b', new Tokens(12, 5))->micros, $prices->costOf('model-c', Tokens::none())->micros]);
    }

    public function testABadConfigurationIsRefused(): void
    {
        $main = ['main' => ['platform' => 'ai.platform.main']];
        $task = ['draft.rewrite' => ['platform' => 'main', 'model' => 'model-a']];

        foreach ([
            [['platforms' => ['Main' => ['platform' => 'x']]], 'A platform name is up to 64 lowercase letters'],
            [['platforms' => [7 => ['platform' => 'x']]], 'A platform name is up to 64 lowercase letters'],
            [['platforms' => ['main' => ['platform' => '']]], 'phil_ai.platforms.main.platform'],
            [['platforms' => ['main' => ['platform' => 'x', 'secret' => 'main-key']]], 'needs a user_key_factory'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'other', 'model' => 'm']]], 'Every task runs on a platform of phil_ai.platforms'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => '']]], 'phil_ai.tasks.draft.rewrite.model'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'm', 'reserve' => 0]]], 'phil_ai.tasks.draft.rewrite.reserve'],
            [['platforms' => $main, 'tasks' => $task, 'quota' => ['tokens_meter' => 'ai_tokens']], 'A quota meter needs phil_ai.usage_meter'],
            [['platforms' => $main, 'tasks' => $task, 'quota' => ['tasks_meter' => 'ai_tasks']], 'A quota meter needs phil_ai.usage_meter'],
            [['platforms' => $main, 'tasks' => $task, 'quota' => ['budget_meter' => 'ai_budget']], 'A quota meter needs phil_ai.usage_meter'],
            [['platforms' => $main, 'tasks' => $task, 'quota' => ['ceiling_meter' => 'ai_ceiling']], 'A quota meter needs phil_ai.usage_meter'],
            [['quota' => ['ceiling_subject' => '']], 'phil_ai.quota.ceiling_subject'],
            [['prices' => ['m' => ['input' => 0, 'output' => 0, 'per_call' => -1]]], 'phil_ai.prices.m.per_call'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'm', 'output' => 'video']]], 'phil_ai.tasks.draft.rewrite.output'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'm', 'tools' => ['look up' => ['description' => 'x']]]]], 'A tool name is 1 to 64 letters'],
            [['platforms' => $main, 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'm', 'tools' => ['look_up' => ['description' => '']]]]], 'phil_ai.tasks.draft.rewrite.tools.look_up.description'],
            [['prices' => ['m' => ['input' => -1, 'output' => 0]]], 'phil_ai.prices.m.input'],
            [['prices' => ['m' => ['input' => 0, 'output' => -1]]], 'phil_ai.prices.m.output'],
            [['key_policy' => 'always'], 'phil_ai.key_policy'],
            [['store' => 'redis'], 'phil_ai.store'],
            [['currency' => ''], 'phil_ai.currency'],
            [['entity_manager' => ''], 'phil_ai.entity_manager'],
            [['translation_domain' => ''], 'phil_ai.translation_domain'],
            [['key_policy' => 'require_user'], 'phil_ai.key_policy "require_user" needs phil/user-secrets-bundle'],
        ] as [$config, $message]) {
            try {
                $this->extension()->load([$config], new ContainerBuilder());
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        self::assertTrue($this->load(['platforms' => $main, 'tasks' => $task, 'usage_meter' => 'meter', 'quota' => ['tokens_meter' => 'ai_tokens']])->hasDefinition(Quota::class));
        self::assertTrue($this->load(['platforms' => [], 'tasks' => []])->hasDefinition(AiTasks::class));
    }

    public function testAPlatformOutOfShapeIsLeftOut(): void
    {
        $container = new ContainerBuilder();
        AiServices::register($container, [
            'platforms' => ['main' => ['platform' => 'ai.platform.main', 'user_key_factory' => null, 'secret' => null], 'broken' => 'ai.platform.broken'],
            'tasks' => [], 'currency' => 'USD', 'prices' => [], 'translation_domain' => 'phil_ai', 'store' => 'memory', 'entity_manager' => 'default',
            'usage_meter' => null, 'quota' => [], 'key_policy' => 'application',
        ], false);

        self::assertEquals([['main' => new Reference('ai.platform.main')], []], $container->getDefinition(SymfonyAiCompletions::class)->getArguments());
    }

    public function testTheMappingIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilAiBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\AiBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('phil_ai')]);

        $this->expectException(InvalidConfigurationException::class);
        $this->load(['key_policy' => 'require_user'], $extension);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, ?ExtensionInterface $extension = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        ($extension ?? $this->extension())->load([$config + ['platforms' => ['main' => ['platform' => 'ai.platform.main']], 'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'model-a']]]], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilAiBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
