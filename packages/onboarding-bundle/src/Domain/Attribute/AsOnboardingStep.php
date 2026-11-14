<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Attribute;

use Attribute;
use Phil\OnboardingBundle\Domain\Flow\Completion;

/**
 * Declares a step of a flow on a class of the application, which the container then autoconfigures; the flow may be
 * declared by the configuration, by another package or by `AsOnboardingFlow`. The arguments are those of
 * `StepDefinition`, after the flow.
 *
 * On a class implementing `StepChecker`, a step done by a checker that names none is checked by that class:
 *
 * ```php
 * #[AsOnboardingStep('author', 'first-page', 'onboarding.author.first_page', Completion::Checker)]
 * final readonly class FirstPageChecker implements StepChecker { … }
 * ```
 *
 * A step done by an answer gives its question as the configuration does:
 *
 * ```php
 * #[AsOnboardingStep('author', 'topics', 'onboarding.author.topics', Completion::Question, question: [
 *     'multiple' => true,
 *     'choices' => [
 *         'news' => ['label' => 'topic.news', 'subscriptions' => ['newsletter.news']],
 *         'events' => ['label' => 'topic.events', 'roles' => ['ROLE_EVENTS']],
 *     ],
 * ])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsOnboardingStep
{
    /**
     * @param array<string, scalar>                                                                                                         $routeParameters
     * @param list<string>                                                                                                                  $before
     * @param list<string>                                                                                                                  $after
     * @param array{multiple?: bool, choices: array<string, array{label: string, roles?: list<string>, subscriptions?: list<string>}>}|null $question        the question of a step done by an answer
     */
    public function __construct(
        public string $flow,
        public string $id,
        public string $title,
        public Completion $completion = Completion::Manual,
        public ?string $description = null,
        public ?string $route = null,
        public array $routeParameters = [],
        public ?string $target = null,
        public ?string $event = null,
        public ?string $checker = null,
        public bool $skippable = true,
        public array $before = [],
        public array $after = [],
        public ?array $question = null,
    ) {
    }
}
