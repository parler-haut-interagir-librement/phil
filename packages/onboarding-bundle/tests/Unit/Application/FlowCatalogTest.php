<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Application;

use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowCatalog::class)]
final class FlowCatalogTest extends TestCase
{
    public function testAQuestionThatBreaksARuleNamesItsStep(): void
    {
        $this->expectExceptionObject(InvalidFlow::because('The step "s": A question offers 2 to 20 choices, not 1.'));

        FlowCatalog::fromDeclarations(['a' => ['title' => 'A', 'description' => null, 'translation_domain' => 'messages']], [Kit::step('a', 's', ['completion' => 'question', 'question' => ['choices' => ['x' => ['label' => 'X']]]])]);
    }

    public function testAChoiceWithANumericIdentifier(): void
    {
        $catalog = FlowCatalog::fromDeclarations(['a' => ['title' => 'A', 'description' => null, 'translation_domain' => 'messages']], [Kit::step('a', 's', ['completion' => 'question', 'question' => ['choices' => [1 => ['label' => 'One'], 2 => ['label' => 'Two']]]])]);

        self::assertSame(['1', '2'], array_map(strval(...), array_keys($catalog->get('a')->step('s')?->question->choices ?? [])));
    }

    public function testAStepWithoutAQuestionGrantsNothing(): void
    {
        $flow = ['a' => ['title' => 'A', 'description' => null, 'translation_domain' => 'messages']];

        self::assertFalse(FlowCatalog::fromDeclarations($flow, [Kit::step('a', 's')])->grantsAnything());
    }

    public function testTheGrantsOfAChoiceAreKept(): void
    {
        $catalog = FlowCatalog::fromDeclarations(['a' => ['title' => 'A', 'description' => null, 'translation_domain' => 'messages']], [Kit::step('a', 's', ['completion' => 'question', 'question' => ['choices' => ['x' => ['label' => 'X', 'roles' => ['ROLE_X'], 'subscriptions' => ['letter.x']], 'y' => ['label' => 'Y']]]])]);
        $choices = $catalog->get('a')->step('s')?->question->choices ?? [];

        self::assertSame([['ROLE_X'], ['letter.x']], [$choices['x']->roles, $choices['x']->subscriptions]);
        self::assertSame([[], []], [$choices['y']->roles, $choices['y']->subscriptions]);
    }

    public function testTheFlowsAndTheirSteps(): void
    {
        $catalog = new Kit()->catalog;

        self::assertSame(['author', 'other', 'member'], array_map(static fn (FlowDefinition $flow): string => $flow->id, $catalog->all()));
        self::assertTrue($catalog->grantsAnything());
        self::assertSame(['news', 'events', 'quiet'], array_keys($catalog->get('member')->step('topics')?->question->choices ?? []));
        self::assertTrue($catalog->get('member')->step('topics')?->question?->multiple);
        self::assertFalse($catalog->get('member')->step('level')?->question?->multiple);
        self::assertFalse(FlowCatalog::fromDeclarations(['a' => ['title' => 'A', 'description' => null, 'translation_domain' => 'messages']], [Kit::step('a', 's', ['completion' => 'question', 'question' => ['choices' => ['x' => ['label' => 'X'], 'y' => ['label' => 'Y']]]])])->grantsAnything());
        self::assertTrue($catalog->has('author'));
        self::assertFalse($catalog->has('nope'));
        self::assertSame(['Other', 'D', 'site'], [$catalog->get('other')->title, $catalog->get('other')->description, $catalog->get('other')->translationDomain]);
        self::assertSame([['flow' => 'author', 'step' => 'publish'], ['flow' => 'other', 'step' => 'first']], $catalog->onEvent('app.published'));
        self::assertSame([], $catalog->onEvent('app.unknown'));
    }

    public function testAStepKeepsEveryFieldOfItsDeclaration(): void
    {
        $catalog = FlowCatalog::fromDeclarations(['f' => ['title' => 'F', 'description' => null, 'translation_domain' => 'messages']], [
            Kit::step('f', 'b'),
            Kit::step('f', 'a', ['title' => 'T', 'completion' => 'checker', 'checker' => 'c', 'description' => 'D', 'route' => 'r', 'route_parameters' => ['id' => 1], 'target' => '#t', 'skippable' => false, 'before' => ['b'], 'after' => ['z']]),
        ]);
        $step = $catalog->get('f')->steps[0];

        self::assertSame(
            ['a', 'T', Completion::Checker, 'D', 'r', ['id' => 1], '#t', null, 'c', false, ['b'], ['z']],
            [$step->id, $step->title, $step->completion, $step->description, $step->route, $step->routeParameters, $step->target, $step->event, $step->checker, $step->skippable, $step->before, $step->after],
        );
    }

    public function testAnUnknownFlowIsRefused(): void
    {
        $this->expectException(UnknownFlow::class);
        $this->expectExceptionMessageIsOrContains('No onboarding flow is declared as "nope".');

        new Kit()->catalog->get('nope');
    }

    public function testAStepOfAnUndeclaredFlowIsRefused(): void
    {
        $this->expectException(InvalidFlow::class);
        $this->expectExceptionMessageIsOrContains('The step "s" belongs to the flow "ghost", which is not declared.');

        FlowCatalog::fromDeclarations([], [Kit::step('ghost', 's')]);
    }

    public function testAnUnknownCompletionIsRefused(): void
    {
        $this->expectException(InvalidFlow::class);
        $this->expectExceptionMessageIsOrContains('The step "s" is completed "magic", not "manual", "event", "checker" or "question".');

        FlowCatalog::fromDeclarations(['f' => ['title' => 'F', 'description' => null, 'translation_domain' => 'messages']], [Kit::step('f', 's', ['completion' => 'magic'])]);
    }

    public function testAFlowWithoutStepsIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The flow "f" has 0 steps');

        FlowCatalog::fromDeclarations(['f' => ['title' => 'F', 'description' => null, 'translation_domain' => 'messages']], []);
    }
}
