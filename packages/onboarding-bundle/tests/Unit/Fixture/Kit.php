<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Application\Onboarding;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The entry point over the in-memory store, a mock clock, a recording dispatcher and one checker, with the flow
 * "author": a manual step, a step done by an event, a step done by the checker, and a step that cannot be skipped; and
 * the flow "member" of two questions, whose grants are recorded.
 */
final readonly class Kit
{
    public MockClock $clock;

    public RecordingRepository $repository;

    public RecordingEvents $events;

    public FixedChecker $checker;

    public RecordingGrants $grants;

    public FlowCatalog $catalog;

    public Onboarding $onboarding;

    public function __construct()
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->repository = new RecordingRepository();
        $this->events = new RecordingEvents();
        $this->checker = new FixedChecker();
        $this->grants = new RecordingGrants();
        $this->catalog = FlowCatalog::fromDeclarations(
            ['author' => ['title' => 'Author', 'description' => null, 'translation_domain' => 'messages'], 'other' => ['title' => 'Other', 'description' => 'D', 'translation_domain' => 'site'], 'member' => ['title' => 'Member', 'description' => null, 'translation_domain' => 'messages']],
            [
                self::step('author', 'profile'),
                self::step('author', 'publish', ['completion' => 'event', 'event' => 'app.published']),
                self::step('author', 'page', ['completion' => 'checker', 'checker' => 'pages']),
                self::step('author', 'rules', ['skippable' => false]),
                self::step('other', 'first', ['completion' => 'event', 'event' => 'app.published']),
                self::step('member', 'topics', ['completion' => 'question', 'question' => ['multiple' => true, 'choices' => [
                    'news' => ['label' => 'topic.news', 'subscriptions' => ['letter.news']],
                    'events' => ['label' => 'topic.events', 'roles' => ['ROLE_EVENTS'], 'subscriptions' => ['letter.events', 'letter.news']],
                    'quiet' => ['label' => 'topic.quiet'],
                ]]]),
                self::step('member', 'level', ['completion' => 'question', 'skippable' => false, 'question' => ['choices' => [
                    'new' => ['label' => 'level.new', 'roles' => ['ROLE_NEW']],
                    'old' => ['label' => 'level.old'],
                ]]]),
            ],
        );
        $this->onboarding = new Onboarding($this->catalog, $this->repository, new ServiceLocator(['pages' => fn (): FixedChecker => $this->checker]), $this->clock, $this->events, $this->grants);
    }

    /**
     * @param array<string, mixed> $with
     *
     * @return array{flow: string, id: string, title: string, completion: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: array{multiple?: bool, choices: array<array-key, array{label: string, roles?: list<string>, subscriptions?: list<string>}>}|null}
     */
    public static function step(string $flow, string $id, array $with = []): array
    {
        /** @var array{flow: string, id: string, title: string, completion: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: array{multiple?: bool, choices: array<array-key, array{label: string, roles?: list<string>, subscriptions?: list<string>}>}|null} $step */
        $step = $with + [
            'flow' => $flow,
            'id' => $id,
            'title' => ucfirst($id),
            'completion' => 'manual',
            'description' => null,
            'route' => null,
            'route_parameters' => [],
            'target' => null,
            'event' => null,
            'checker' => null,
            'skippable' => true,
            'before' => [],
            'after' => [],
        ];

        return $step;
    }
}
