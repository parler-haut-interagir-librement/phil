<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\DependencyInjection;

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
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingController;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Privacy\OnboardingPrivacy;
use Phil\OnboardingBundle\Infrastructure\Security\SecurityCurrentUser;
use Phil\OnboardingBundle\Infrastructure\Twig\ChecklistComponent;
use Phil\OnboardingBundle\Infrastructure\Twig\KitComponents;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingExtension;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingView;
use Phil\OnboardingBundle\Infrastructure\Twig\TourComponent;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;

/**
 * The bundle's services: the store, the current user, the tour preference of the configuration or everyone's, the
 * grants of the answers, the catalog of the declared flows, the entry point and its event listener, the endpoints, and,
 * when Twig is installed, the checklist and the tour with the names of the recipes of the kit.
 *
 * @phpstan-import-type QuestionDeclaration from FlowCatalog
 *
 * @phpstan-type StepConfig array{title: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, completion: string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: QuestionDeclaration}
 * @phpstan-type FlowConfig array{title: string, description: ?string, translation_domain: string, steps: array<string, StepConfig>}
 * @phpstan-type Config array{store: string, entity_manager: string, current_user: ?string, answer_grants: ?string, kit_subdirectory: ?string, tour_preference: ?string, flows: array<string, FlowConfig>}
 */
final class OnboardingServices
{
    public const string DECLARATION_TAG = 'phil_onboarding.declaration';

    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::store($container, $config);

        if (null === $config['current_user']) {
            $container->register(SecurityCurrentUser::class, SecurityCurrentUser::class)
                ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $container->setAlias(CurrentUser::class, $config['current_user'] ?? SecurityCurrentUser::class);
        if (null === $config['tour_preference']) {
            $container->register(AlwaysTours::class, AlwaysTours::class);
        }

        $container->setAlias(TourPreference::class, $config['tour_preference'] ?? AlwaysTours::class);

        if (null === $config['answer_grants']) {
            $container->register(NoAnswerGrants::class, NoAnswerGrants::class);
        }

        $container->setAlias(AnswerGrants::class, $config['answer_grants'] ?? NoAnswerGrants::class);

        [$flows, $steps] = self::declarations($config['flows']);
        $container->register(FlowCatalog::class, FlowCatalog::class)
            ->setFactory([FlowCatalog::class, 'fromDeclarations'])
            ->setArguments([$flows, $steps])
            ->setPublic(true);
        $container->register(Onboarding::class, Onboarding::class)
            ->setArguments([
                new Reference(FlowCatalog::class),
                new Reference(ProgressRepository::class),
                null,
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(AnswerGrants::class),
            ])
            ->setPublic(true);
        $container->register(EventCompletion::class, EventCompletion::class)
            ->setArguments([new Reference(FlowCatalog::class), new Reference(Onboarding::class), new Reference(CurrentUser::class)]);

        $container->register(OnboardingRequest::class, OnboardingRequest::class)
            ->setArguments([new Reference(self::TOKEN_MANAGER)]);
        $container->register(OnboardingController::class, OnboardingController::class)
            ->setArguments([new Reference(Onboarding::class), new Reference(CurrentUser::class), new Reference(OnboardingRequest::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(OnboardingPrivacy::class, OnboardingPrivacy::class)
                ->setArguments([new Reference(Onboarding::class), new Reference(ProgressRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        self::twig($container, $config['kit_subdirectory']);

        $tag = static function (ChildDefinition $definition): void {
            $definition->addTag(self::DECLARATION_TAG);
        };
        $container->registerAttributeForAutoconfiguration(AsOnboardingFlow::class, $tag);
        $container->registerAttributeForAutoconfiguration(AsOnboardingStep::class, $tag);
    }

    /**
     * The flows and the steps of the configuration, as `FlowCatalog::fromDeclarations()` takes them.
     *
     * @param array<string, FlowConfig> $configured
     *
     * @return array{array<string, array{title: string, description: ?string, translation_domain: string}>, list<array<string, mixed>>}
     */
    private static function declarations(array $configured): array
    {
        $flows = [];
        $steps = [];

        foreach ($configured as $id => $flow) {
            $flows[$id] = ['title' => $flow['title'], 'description' => $flow['description'], 'translation_domain' => $flow['translation_domain']];

            foreach ($flow['steps'] as $step => $declaration) {
                $steps[] = ['flow' => $id, 'id' => $step] + $declaration;
            }
        }

        return [$flows, $steps];
    }

    /**
     * @param Config $config
     */
    private static function store(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryProgressRepository::class, InMemoryProgressRepository::class);
            $container->setAlias(ProgressRepository::class, InMemoryProgressRepository::class)->setPublic(true);

            return;
        }

        $container->register(DoctrineProgressRepository::class, DoctrineProgressRepository::class)
            ->setArguments([new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager')]);
        $container->setAlias(ProgressRepository::class, DoctrineProgressRepository::class)->setPublic(true);
        $container->register(BinaryKeysListener::class, BinaryKeysListener::class)
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    }

    private static function twig(ContainerBuilder $container, ?string $kitSubdirectory): void
    {
        if (!class_exists(Environment::class)) {
            return;
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$kitSubdirectory]);

        $container->register(OnboardingView::class, OnboardingView::class)
            ->setArguments([
                new Reference(Onboarding::class),
                new Reference(CurrentUser::class),
                new Reference(OnboardingRequest::class),
                new Reference('router'),
                new Reference(TourPreference::class),
            ])
            ->setPublic(true);
        $container->register(OnboardingExtension::class, OnboardingExtension::class)
            ->setArguments([new Reference(OnboardingView::class), new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        if (!class_exists(AsTwigComponent::class)) {
            return;
        }

        foreach ([ChecklistComponent::class, TourComponent::class] as $component) {
            $container->register($component, $component)
                ->setArguments([new Reference(OnboardingView::class)])
                ->setAutoconfigured(true);
        }
    }
}
