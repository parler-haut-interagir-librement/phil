<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\OnboardingBundle\Application\AlwaysTours;
use Phil\OnboardingBundle\Application\Contract\AnswerGrants;
use Phil\OnboardingBundle\Application\Contract\StepChecker;
use Phil\OnboardingBundle\Application\Contract\TourPreference;
use Phil\OnboardingBundle\Application\EventCompletion;
use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Application\NoAnswerGrants;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference;
use Phil\SettingsBundle\Application\Settings;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds to the catalog the flows and steps the application declares with attributes, then builds the catalog once, so
 * that a mistake in any declaration — a step twice, a flow missing, a checker that is not one, a circular order — stops
 * the container with its reason rather than the first page that reads a flow. It then hands the checkers to the entry
 * point and listens to the events that complete steps. When phil/settings-bundle is installed and the configuration names
 * no tour preference, the users' setting `onboarding.tours` decides who sees the tours.
 *
 * It stops the container when a choice of a question grants a role or a subscription and the application names no
 * `AnswerGrants`: the bundle never writes them itself. It also stops the container when the application turns the CSRF protection off: the endpoints check their stateless
 * token with Symfony's token manager.
 *
 * @phpstan-import-type QuestionDeclaration from FlowCatalog
 */
final readonly class OnboardingPass implements CompilerPassInterface
{
    public const string LISTENER_TAG = 'kernel.event_listener';

    /**
     * @throws LogicException when the CSRF protection is off
     * @throws InvalidFlow    when a declaration breaks a rule
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FlowCatalog::class)) {
            return;
        }

        if (!$container->has(OnboardingServices::TOKEN_MANAGER)) {
            throw new LogicException('phil/onboarding-bundle checks the changes of the progress with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "phil_onboarding" is stateless and needs no session.');
        }

        $catalog = $container->getDefinition(FlowCatalog::class);
        /** @var array<string, array{title: string, description: ?string, translation_domain: string}> $flows */
        $flows = $catalog->getArgument(0);
        /** @var list<array{flow: string, id: string, title: string, completion: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: QuestionDeclaration|null}> $steps */
        $steps = $catalog->getArgument(1);

        foreach (array_keys($container->findTaggedServiceIds(OnboardingServices::DECLARATION_TAG)) as $id) {
            $reflection = $container->getReflectionClass($container->getDefinition($id)->getClass());

            if (!$reflection instanceof ReflectionClass) {
                continue;
            }

            foreach ($reflection->getAttributes(AsOnboardingFlow::class) as $attribute) {
                $flow = $attribute->newInstance();

                if (isset($flows[$flow->id])) {
                    throw InvalidFlow::because(\sprintf('The flow "%s" is declared twice.', $flow->id));
                }

                $flows[$flow->id] = ['title' => $flow->title, 'description' => $flow->description, 'translation_domain' => $flow->translationDomain];
            }

            foreach ($reflection->getAttributes(AsOnboardingStep::class) as $attribute) {
                $step = $attribute->newInstance();
                $checker = $step->checker;

                if (null === $checker && Completion::Checker === $step->completion && $reflection->implementsInterface(StepChecker::class)) {
                    $checker = $id;
                }

                $steps[] = [
                    'flow' => $step->flow,
                    'id' => $step->id,
                    'title' => $step->title,
                    'completion' => $step->completion->value,
                    'description' => $step->description,
                    'route' => $step->route,
                    'route_parameters' => $step->routeParameters,
                    'target' => $step->target,
                    'event' => $step->event,
                    'checker' => $checker,
                    'skippable' => $step->skippable,
                    'before' => $step->before,
                    'after' => $step->after,
                    'question' => $step->question,
                ];
            }
        }

        $catalog->setArguments([$flows, $steps]);
        $this->checkGrants($container, FlowCatalog::fromDeclarations($flows, $steps));

        $checkers = [];
        $events = [];

        foreach ($steps as $step) {
            if (null !== $step['checker']) {
                $checkers[$step['checker']] = $this->checker($container, $step['checker'], $step['id']);
            }

            if (null !== $step['event']) {
                $events[$step['event']] = $step['event'];
            }
        }

        $container->getDefinition(Onboarding::class)->replaceArgument(2, ServiceLocatorTagPass::register($container, $checkers));

        foreach ($events as $event) {
            $container->getDefinition(EventCompletion::class)->addTag(self::LISTENER_TAG, ['event' => $event, 'method' => '__invoke']);
        }

        if (AlwaysTours::class === (string) $container->getAlias(TourPreference::class) && $container->has(Settings::class)) {
            $container->register(SettingsTourPreference::class, SettingsTourPreference::class)->setArguments([new Reference(Settings::class)]);
            $container->setAlias(TourPreference::class, SettingsTourPreference::class);
        }
    }

    /**
     * @throws InvalidFlow when a choice grants something and the application names no AnswerGrants
     */
    private function checkGrants(ContainerBuilder $container, FlowCatalog $catalog): void
    {
        if ($catalog->grantsAnything() && NoAnswerGrants::class === (string) $container->getAlias(AnswerGrants::class)) {
            throw InvalidFlow::because('A choice of a question grants roles or subscriptions, which the bundle never writes itself: name the AnswerGrants service of the application that grants them, in "phil_onboarding.answer_grants".');
        }
    }

    /**
     * @throws InvalidFlow when the service does not exist or is not a StepChecker
     */
    private function checker(ContainerBuilder $container, string $id, string $step): Reference
    {
        $class = $container->has($id) ? $container->findDefinition($id)->getClass() : null;

        if (null === $class || !($container->getReflectionClass($class)?->implementsInterface(StepChecker::class) ?? false)) {
            throw InvalidFlow::because(\sprintf('The step "%s" is checked by "%s", which is not a service implementing StepChecker.', $step, $id));
        }

        return new Reference($id);
    }
}
