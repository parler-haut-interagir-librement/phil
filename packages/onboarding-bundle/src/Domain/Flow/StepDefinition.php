<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * One step of a flow: its identifier, the translation keys of its title and description, where it leads — a route
 * of the application and the CSS selector of the element a tour highlights —, how it gets done, whether a person may
 * skip it, and where it comes among the steps declared by other packages.
 *
 * A step done by an event names the event, as the dispatcher names it — usually its class —; a step done by a
 * checker names the service of the checker; a step done by an answer has its question. None names anything for another
 * completion.
 */
final readonly class StepDefinition
{
    public const int MAX_TITLE = 255;

    public const int MAX_DESCRIPTION = 2000;

    public const int MAX_SELECTOR = 255;

    /**
     * @param array<string, scalar> $routeParameters
     * @param list<string>          $before          the steps this one comes before
     * @param list<string>          $after           the steps this one comes after
     *
     * @throws InvalidFlow when the declaration breaks a rule
     */
    public function __construct(
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
        public ?Question $question = null,
    ) {
        Identifier::check($id, 'step');
        Identifier::text($title, \sprintf('title of the step "%s"', $id), self::MAX_TITLE);

        if (null !== $description) {
            Identifier::text($description, \sprintf('description of the step "%s"', $id), self::MAX_DESCRIPTION);
        }

        if (null !== $route) {
            Identifier::text($route, \sprintf('route of the step "%s"', $id), self::MAX_TITLE);
        }

        if (null !== $target) {
            Identifier::text($target, \sprintf('target of the step "%s"', $id), self::MAX_SELECTOR);
        }

        if ((Completion::Event === $completion) !== (null !== $event) || (Completion::Checker === $completion) !== (null !== $checker)) {
            throw InvalidFlow::because(\sprintf('The step "%s" names an event exactly when it is done by an event, and a checker exactly when it is done by a checker.', $id));
        }

        if ((Completion::Question === $completion) !== $question instanceof Question) {
            throw InvalidFlow::because(\sprintf('The step "%s" has a question exactly when it is done by an answer.', $id));
        }

        if ('' === $event || '' === $checker) {
            throw InvalidFlow::because(\sprintf('The step "%s" names an empty event or checker.', $id));
        }
    }
}
