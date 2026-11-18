<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit;

use Phil\OnboardingBundle\Application\AlwaysTours;
use Phil\OnboardingBundle\Application\Contract\AnswerGrants;
use Phil\OnboardingBundle\Application\Contract\CurrentUser;
use Phil\OnboardingBundle\Application\Contract\TourPreference;
use Phil\OnboardingBundle\Application\EventCompletion;
use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Application\NoAnswerGrants;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingPass;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingServices;
use Phil\OnboardingBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingController;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Privacy\OnboardingPrivacy;
use Phil\OnboardingBundle\Infrastructure\Security\SecurityCurrentUser;
use Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference;
use Phil\OnboardingBundle\Infrastructure\Twig\ChecklistComponent;
use Phil\OnboardingBundle\Infrastructure\Twig\KitComponents;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingExtension;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingView;
use Phil\OnboardingBundle\Infrastructure\Twig\TourComponent;
use Phil\OnboardingBundle\PhilOnboardingBundle;
use Phil\OnboardingBundle\Tests\Unit\Fixture\AfterTheGhost;
use Phil\OnboardingBundle\Tests\Unit\Fixture\AuthorOnboarding;
use Phil\OnboardingBundle\Tests\Unit\Fixture\ExtensionStub;
use Phil\OnboardingBundle\Tests\Unit\Fixture\NotAChecker;
use Phil\OnboardingBundle\Tests\Unit\Fixture\PagesChecker;
use Phil\SettingsBundle\Application\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(PhilOnboardingBundle::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(NoAnswerGrants::class)]
#[CoversClass(OnboardingServices::class)]
#[CoversClass(OnboardingPass::class)]
#[CoversClass(SettingsTourPreference::class)]
final class PhilOnboardingBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_onboarding', $this->extension()->getAlias());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineProgressRepository::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $container->getDefinition(BinaryKeysListener::class)->getTags());
        self::assertSame([DoctrineProgressRepository::class, true], [(string) $container->getAlias(ProgressRepository::class), $container->getAlias(ProgressRepository::class)->isPublic()]);
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SecurityCurrentUser::class)->getArguments());
        self::assertSame(SecurityCurrentUser::class, (string) $container->getAlias(CurrentUser::class));
        self::assertSame(AlwaysTours::class, (string) $container->getAlias(TourPreference::class));
        self::assertTrue($container->hasDefinition(AlwaysTours::class));
        self::assertFalse($container->hasDefinition(SettingsTourPreference::class));

        $catalog = $container->getDefinition(FlowCatalog::class);
        self::assertSame([FlowCatalog::class, 'fromDeclarations'], $catalog->getFactory());
        self::assertSame([[], []], $catalog->getArguments());
        self::assertTrue($catalog->isPublic());
        self::assertEquals([
            new Reference(FlowCatalog::class),
            new Reference(ProgressRepository::class),
            null,
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(AnswerGrants::class),
        ], $container->getDefinition(Onboarding::class)->getArguments());
        self::assertSame(NoAnswerGrants::class, (string) $container->getAlias(AnswerGrants::class));
        self::assertTrue($container->hasDefinition(NoAnswerGrants::class));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(Onboarding::class)->isPublic());
        self::assertEquals([new Reference(FlowCatalog::class), new Reference(Onboarding::class), new Reference(CurrentUser::class)], $container->getDefinition(EventCompletion::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(OnboardingRequest::class)->getArguments());

        $controller = $container->getDefinition(OnboardingController::class);
        self::assertEquals([new Reference(Onboarding::class), new Reference(CurrentUser::class), new Reference(OnboardingRequest::class)], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());

        self::assertEquals([
            new Reference(Onboarding::class),
            new Reference(CurrentUser::class),
            new Reference(OnboardingRequest::class),
            new Reference('router'),
            new Reference(TourPreference::class),
        ], $container->getDefinition(OnboardingView::class)->getArguments());
        self::assertTrue($container->getDefinition(OnboardingView::class)->isPublic());
        self::assertEquals([new Reference(OnboardingView::class), new Reference(KitComponents::class)], $container->getDefinition(OnboardingExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(OnboardingExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(OnboardingExtension::class)->hasTag('twig.runtime'));

        foreach ([ChecklistComponent::class, TourComponent::class] as $component) {
            self::assertEquals([new Reference(OnboardingView::class)], $container->getDefinition($component)->getArguments());
            self::assertTrue($container->getDefinition($component)->isAutoconfigured());
        }

        self::assertArrayHasKey(AsOnboardingFlow::class, $container->getAttributeAutoconfigurators());
        self::assertArrayHasKey(AsOnboardingStep::class, $container->getAttributeAutoconfigurators());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'current_user' => 'app.user',
            'tour_preference' => 'app.tours',
            'answer_grants' => 'app.grants',
            'kit_subdirectory' => 'ui',
            'flows' => [
                'first-steps' => [
                    'title' => 'T',
                    'steps' => [
                        'fill-profile' => ['title' => 'P', 'route' => 'app_profile', 'route_parameters' => ['tab' => 'bio'], 'target' => '#p', 'skippable' => false, 'before' => ['b'], 'after' => ['a']],
                        'publish' => ['title' => 'Q', 'description' => 'D', 'completion' => 'event', 'event' => 'app.published'],
                    ],
                ],
            ],
        ]);

        self::assertSame([InMemoryProgressRepository::class, true], [(string) $container->getAlias(ProgressRepository::class), $container->getAlias(ProgressRepository::class)->isPublic()]);
        self::assertFalse($container->hasDefinition(DoctrineProgressRepository::class));
        self::assertFalse($container->hasDefinition(BinaryKeysListener::class));
        self::assertTrue($container->hasDefinition(InMemoryProgressRepository::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentUser::class));
        self::assertSame('app.user', (string) $container->getAlias(CurrentUser::class));
        self::assertSame('app.tours', (string) $container->getAlias(TourPreference::class));
        self::assertFalse($container->hasDefinition(AlwaysTours::class));
        self::assertSame('app.grants', (string) $container->getAlias(AnswerGrants::class));
        self::assertFalse($container->hasDefinition(NoAnswerGrants::class));
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([
            ['first-steps' => ['title' => 'T', 'description' => null, 'translation_domain' => 'messages']],
            [
                ['flow' => 'first-steps', 'id' => 'fill-profile', 'title' => 'P', 'route' => 'app_profile', 'route_parameters' => ['tab' => 'bio'], 'target' => '#p', 'skippable' => false, 'before' => ['b'], 'after' => ['a'], 'description' => null, 'completion' => 'manual', 'event' => null, 'checker' => null],
                ['flow' => 'first-steps', 'id' => 'publish', 'title' => 'Q', 'description' => 'D', 'completion' => 'event', 'event' => 'app.published', 'route' => null, 'route_parameters' => [], 'target' => null, 'checker' => null, 'skippable' => true, 'before' => [], 'after' => []],
            ],
        ], $this->sorted($container->getDefinition(FlowCatalog::class)->getArguments()));
        self::assertEquals([new Reference('doctrine.orm.onboarding_entity_manager')], $this->load(['store' => 'doctrine', 'entity_manager' => 'onboarding'])->getDefinition(DoctrineProgressRepository::class)->getArguments());
    }

    public function testThePrivacyPortsAreDeclared(): void
    {
        $definition = $this->load([])->getDefinition(OnboardingPrivacy::class);

        self::assertEquals([new Reference(Onboarding::class), new Reference(ProgressRepository::class)], $definition->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $definition->getTags());
    }

    public function testTheSettingsBundleGivesTheTourPreference(): void
    {
        $container = $this->load([]);
        $container->register('security.csrf.token_manager');
        $container->register(Settings::class, Settings::class);

        $this->compile($container);

        self::assertSame(SettingsTourPreference::class, (string) $container->getAlias(TourPreference::class));
        self::assertEquals([new Reference(Settings::class)], $container->getDefinition(SettingsTourPreference::class)->getArguments());
        self::assertSame('onboarding.tours', SettingsTourPreference::KEY);

        $configured = $this->load(['tour_preference' => 'app.tours']);
        $configured->register('security.csrf.token_manager');
        $configured->register(Settings::class, Settings::class);
        $this->compile($configured);
        self::assertSame('app.tours', (string) $configured->getAlias(TourPreference::class));
        self::assertFalse($configured->hasDefinition(SettingsTourPreference::class));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['store' => 'redis'], ['entity_manager' => ''], ['flows' => ['f' => ['steps' => []]]], ['flows' => ['f' => ['title' => 'F', 'steps' => ['s' => ['title' => 'S', 'completion' => 'magic']]]]]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_onboarding', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheOtherBundlesArePrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new ExtensionStub('doctrine'));
        $container->registerExtension(new ExtensionStub('framework'));
        $container->registerExtension(new ExtensionStub('phil_settings'));
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilOnboardingBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\OnboardingBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([
            ['csrf_protection' => ['stateless_token_ids' => ['phil_onboarding']]],
            ['asset_mapper' => ['paths' => [PhilOnboardingBundle::CONTROLLERS_DIR => '@phil/onboarding-bundle', PhilOnboardingBundle::STYLES_DIR => 'phil-onboarding']]],
        ], $container->getExtensionConfig('framework'));
        self::assertSame([['settings' => ['onboarding.tours' => [
            'type' => 'boolean',
            'default' => true,
            'section' => 'general',
            'help' => 'onboarding.tours.help',
            'translation_domain' => 'phil_onboarding',
        ]]]], $container->getExtensionConfig('phil_settings'));
        self::assertDirectoryExists(PhilOnboardingBundle::CONTROLLERS_DIR);
        self::assertDirectoryExists(PhilOnboardingBundle::STYLES_DIR);

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], [], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('framework'), $bare->getExtensionConfig('phil_settings')]);
    }

    public function testTheDeclarationsOfAttributesAreAddedAndChecked(): void
    {
        $container = $this->load(['flows' => ['reader' => ['title' => 'Reader', 'steps' => ['subscribe' => ['title' => 'S', 'completion' => 'event', 'event' => 'app.subscribed'], 'resubscribe' => ['title' => 'R', 'completion' => 'event', 'event' => 'app.subscribed']]]]]);
        $container->register('security.csrf.token_manager');
        $container->register(AuthorOnboarding::class, AuthorOnboarding::class)->setAutoconfigured(true);
        $container->register(PagesChecker::class, PagesChecker::class)->setAutoconfigured(true);
        $container->register('unrelated', stdClass::class)->addTag(OnboardingServices::DECLARATION_TAG);
        $container->register('ghost', 'App\Missing')->addTag(OnboardingServices::DECLARATION_TAG);
        $container->register('after-the-ghost', AfterTheGhost::class)->addTag(OnboardingServices::DECLARATION_TAG);

        $this->compile($container);

        /** @var array<string, array{title: string, description: ?string, translation_domain: string}> $flows */
        $flows = $container->getDefinition(FlowCatalog::class)->getArgument(0);
        /** @var list<array<string, mixed>> $steps */
        $steps = $container->getDefinition(FlowCatalog::class)->getArgument(1);
        self::assertSame(['reader' => ['title' => 'Reader', 'description' => null, 'translation_domain' => 'messages'], 'author' => ['title' => 'Author', 'description' => 'Welcome', 'translation_domain' => 'site']], $flows);
        self::assertSame([['reader', 'subscribe'], ['reader', 'resubscribe'], ['author', 'publish'], ['reader', 'follow'], ['author', 'page'], ['author', 'hand'], ['reader', 'ghosted']], array_map(static fn (array $step): array => [$step['flow'], $step['id']], $steps));
        self::assertSame([
            'flow' => 'author', 'id' => 'publish', 'title' => 'Publish', 'completion' => 'event', 'description' => 'D', 'route' => 'app_page', 'route_parameters' => ['id' => 1],
            'target' => '#page', 'event' => 'app.published', 'checker' => null, 'skippable' => false, 'before' => ['x'], 'after' => ['y'], 'question' => null,
        ], $steps[2]);
        self::assertSame([PagesChecker::class, PagesChecker::class, null], [$steps[3]['checker'], $steps[4]['checker'], $steps[5]['checker']]);

        $locator = $container->getDefinition(Onboarding::class)->getArgument(2);
        self::assertInstanceOf(Reference::class, $locator);
        $definition = $container->getDefinition((string) $locator);
        self::assertSame(ServiceLocator::class, $definition->getClass());
        self::assertSame([['event' => 'app.subscribed', 'method' => '__invoke'], ['event' => 'app.published', 'method' => '__invoke']], $container->getDefinition(EventCompletion::class)->getTag('kernel.event_listener'));
    }

    public function testAFlowDeclaredTwiceIsRefused(): void
    {
        $container = $this->load(['flows' => ['author' => ['title' => 'A', 'steps' => ['s' => ['title' => 'S']]]]]);
        $container->register('security.csrf.token_manager');
        $container->register(AuthorOnboarding::class, AuthorOnboarding::class)->setAutoconfigured(true);

        $this->expectException(InvalidFlow::class);
        $this->expectExceptionMessageIsOrContains('The flow "author" is declared twice.');

        $this->compile($container);
    }

    public function testACheckerThatIsNoneIsRefused(): void
    {
        foreach (['absent', 'not a checker', 'no such class'] as $case) {
            $container = $this->load(['flows' => ['reader' => ['title' => 'R', 'steps' => ['follow' => ['title' => 'F', 'completion' => 'checker', 'checker' => 'missing']]]]]);
            $container->register('security.csrf.token_manager');

            if ('not a checker' === $case) {
                $container->register('missing', stdClass::class);
            }

            if ('no such class' === $case) {
                $container->register('missing', 'App\Missing');
            }

            try {
                $this->compile($container);
                self::fail($case);
            } catch (InvalidFlow $invalidFlow) {
                self::assertSame('The step "follow" is checked by "missing", which is not a service implementing StepChecker.', $invalidFlow->getMessage());
            }
        }
    }

    public function testAStepCheckedByANonCheckerWithoutNamingOneIsRefused(): void
    {
        $container = $this->load(['flows' => ['reader' => ['title' => 'R', 'steps' => ['s' => ['title' => 'S']]]]]);
        $container->register('security.csrf.token_manager');
        $container->register(NotAChecker::class, NotAChecker::class)->setAutoconfigured(true);

        $this->expectException(InvalidFlow::class);
        $this->expectExceptionMessageIsOrContains('The step "lost" names an event exactly when it is done by an event');

        $this->compile($container);
    }

    public function testAQuestionOfTheConfiguration(): void
    {
        $question = ['multiple' => true, 'choices' => ['news' => ['label' => 'N', 'subscriptions' => ['letter.news']], 'events' => ['label' => 'E', 'roles' => ['ROLE_EVENTS']]]];
        $container = $this->load(['answer_grants' => 'app.grants', 'flows' => ['member' => ['title' => 'M', 'steps' => ['topics' => ['title' => 'T', 'completion' => 'question', 'question' => $question]]]]]);
        $container->register('security.csrf.token_manager');

        $this->compile($container);

        /** @var list<array<string, mixed>> $steps */
        $steps = $container->getDefinition(FlowCatalog::class)->getArgument(1);
        self::assertSame(['multiple' => true, 'choices' => ['news' => ['label' => 'N', 'subscriptions' => ['letter.news'], 'roles' => []], 'events' => ['label' => 'E', 'roles' => ['ROLE_EVENTS'], 'subscriptions' => []]]], $steps[0]['question']);
        /** @var list<array{question: array{multiple: bool}}> $single */
        $single = $this->load(['flows' => ['member' => ['title' => 'M', 'steps' => ['s' => ['title' => 'S', 'question' => ['choices' => ['a' => ['label' => 'A'], 'b' => ['label' => 'B']]]]]]]])->getDefinition(FlowCatalog::class)->getArgument(1);
        self::assertFalse($single[0]['question']['multiple']);
    }

    public function testTheIdentifiersOfTheChoicesAreKeptAsWritten(): void
    {
        $question = ['choices' => ['my-choice' => ['label' => 'A'], 'other-one' => ['label' => 'B']]];
        $container = $this->load(['flows' => ['member' => ['title' => 'M', 'steps' => ['s' => ['title' => 'S', 'completion' => 'question', 'question' => $question]]]]]);

        /** @var list<array{question: array{choices: array<string, mixed>}}> $steps */
        $steps = $container->getDefinition(FlowCatalog::class)->getArgument(1);
        self::assertSame(['my-choice', 'other-one'], array_keys($steps[0]['question']['choices']));
    }

    public function testAGrantingQuestionNeedsTheGrantsOfTheApplication(): void
    {
        $question = ['choices' => ['a' => ['label' => 'A', 'roles' => ['ROLE_A']], 'b' => ['label' => 'B']]];
        $container = $this->load(['flows' => ['member' => ['title' => 'M', 'steps' => ['s' => ['title' => 'S', 'completion' => 'question', 'question' => $question]]]]]);
        $container->register('security.csrf.token_manager');

        $this->expectExceptionObject(InvalidFlow::because('A choice of a question grants roles or subscriptions, which the bundle never writes itself: name the AnswerGrants service of the application that grants them, in "phil_onboarding.answer_grants".'));

        $this->compile($container);
    }

    public function testAQuestionThatGrantsNothingNeedsNoGrants(): void
    {
        $question = ['choices' => ['a' => ['label' => 'A'], 'b' => ['label' => 'B']]];
        $container = $this->load(['flows' => ['member' => ['title' => 'M', 'steps' => ['s' => ['title' => 'S', 'completion' => 'question', 'question' => $question]]]]]);
        $container->register('security.csrf.token_manager');

        $this->compile($container);

        self::assertSame(NoAnswerGrants::class, (string) $container->getAlias(AnswerGrants::class));
    }

    public function testTheKitSubdirectoryOfTheToolkit(): void
    {
        self::assertSame(['ui'], $this->subdirectory([], 'ui'));
        self::assertSame([''], $this->subdirectory([], null));
        self::assertSame(['mine'], $this->subdirectory(['kit_subdirectory' => 'mine'], 'ui'));
        self::assertSame([''], $this->subdirectory([], 42));

        $bare = new ContainerBuilder();
        new KitSubdirectoryPass()->process($bare);
        self::assertSame(['service_container'], array_keys($bare->getDefinitions()));
        self::assertSame('ui:Button', new KitComponents('/ui/')->name('Button'));
        self::assertSame('ui:forms:Button', new KitComponents('ui/forms')->name('Button'));
        self::assertSame('Button', new KitComponents()->name('Button'));
    }

    public function testTheCsrfProtectionIsRequired(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');

        $this->compile($this->load([]));
    }

    public function testThePassLeavesAContainerWithoutTheBundleAlone(): void
    {
        $container = new ContainerBuilder();
        new OnboardingPass()->process($container);

        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function subdirectory(array $config, string|int|null $toolkit): array
    {
        $container = $this->load($config);

        if (null !== $toolkit) {
            $container->setParameter('.ux_toolkit.components.subdirectory', $toolkit);
        }

        new KitSubdirectoryPass()->process($container);

        return $container->getDefinition(KitComponents::class)->getArguments();
    }

    private function compile(ContainerBuilder $container): void
    {
        new AttributeAutoconfigurationPass()->process($container);
        new ResolveInstanceofConditionalsPass()->process($container);
        new OnboardingPass()->process($container);
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return array<array-key, mixed>
     */
    private function sorted(array $arguments): array
    {
        /** @var list<array<string, mixed>> $steps */
        $steps = $arguments[1];

        return [$arguments[0], $steps];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilOnboardingBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
