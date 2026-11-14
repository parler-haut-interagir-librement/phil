<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Domain;

use Closure;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Exception\OnboardingException;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;
use Phil\OnboardingBundle\Domain\Flow\Identifier;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowDefinition::class)]
#[CoversClass(StepDefinition::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidFlow::class)]
final class FlowDefinitionTest extends TestCase
{
    public function testAFlowKeepsItsStepsInOrder(): void
    {
        $flow = new FlowDefinition('author', 'onboarding.author', [
            new StepDefinition('profile', 'Profile', after: ['first-page']),
            new StepDefinition('first-page', 'First page'),
        ], 'Welcome', 'site');

        self::assertSame(['first-page', 'profile'], array_map(static fn (StepDefinition $step): string => $step->id, $flow->steps));
        self::assertSame('Profile', $flow->step('profile')?->title);
        self::assertNull($flow->step('missing'));
        self::assertSame(['author', 'onboarding.author', 'Welcome', 'site'], [$flow->id, $flow->title, $flow->description, $flow->translationDomain]);
        self::assertSame('messages', new FlowDefinition('a', 'A', [new StepDefinition('s', 'S')])->translationDomain);
    }

    public function testAStepKeepsItsDeclaration(): void
    {
        $step = new StepDefinition('first.page_1-a', 'Title', Completion::Event, 'Description', 'app_page', ['id' => 3], '#page', 'App\Event', null, false, ['x'], ['y']);

        self::assertSame(
            ['first.page_1-a', 'Title', Completion::Event, 'Description', 'app_page', ['id' => 3], '#page', 'App\Event', null, false, ['x'], ['y']],
            [$step->id, $step->title, $step->completion, $step->description, $step->route, $step->routeParameters, $step->target, $step->event, $step->checker, $step->skippable, $step->before, $step->after],
        );

        $checked = new StepDefinition('s', 'S', Completion::Checker, checker: 'app.checker');
        self::assertSame(['app.checker', true, Completion::Checker], [$checked->checker, $checked->skippable, $checked->completion]);
        self::assertSame(Completion::Manual, new StepDefinition('s', 'S')->completion);
    }

    public function testTheLimitsAreInclusive(): void
    {
        $id = str_repeat('a', 64);
        $text = str_repeat('é', StepDefinition::MAX_TITLE);
        $step = new StepDefinition($id, $text, description: str_repeat('é', StepDefinition::MAX_DESCRIPTION), route: $text, target: str_repeat('é', StepDefinition::MAX_SELECTOR));
        self::assertSame($id, $step->id);

        $steps = array_map(static fn (int $i): StepDefinition => new StepDefinition('s'.$i, 'S'), range(1, FlowDefinition::MAX_STEPS));
        self::assertCount(100, new FlowDefinition('f', $text, $steps, str_repeat('é', StepDefinition::MAX_DESCRIPTION), str_repeat('d', 64))->steps);
        self::assertSame('a.B_c+d-1', new FlowDefinition('f', 'F', [$step], translationDomain: 'a.B_c+d-1')->translationDomain);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'step identifier' => [static fn (): StepDefinition => new StepDefinition('Profile', 'P'), 'The step "Profile" is not made of up to 64 lowercase letters, digits, "_", "." and "-", starting with a letter or a digit.'];
        yield 'leading dash' => [static fn (): StepDefinition => new StepDefinition('-a', 'P'), 'The step "-a" is not'];
        yield 'too long identifier' => [static fn (): StepDefinition => new StepDefinition(str_repeat('a', 65), 'P'), 'is not made'];
        yield 'trailing new line' => [static fn (): StepDefinition => new StepDefinition("a\n", 'P'), 'is not made'];
        yield 'empty title' => [static fn (): StepDefinition => new StepDefinition('a', ' '), 'The title of the step "a" " " is empty, longer than 255 characters or holds a control character.'];
        yield 'long title' => [static fn (): StepDefinition => new StepDefinition('a', str_repeat('a', 256)), 'longer than 255'];
        yield 'control character' => [static fn (): StepDefinition => new StepDefinition('a', "a\tb"), 'control character'];
        yield 'delete character' => [static fn (): StepDefinition => new StepDefinition('a', "a\x7Fb"), 'control character'];
        yield 'description' => [static fn (): StepDefinition => new StepDefinition('a', 'A', description: str_repeat('a', 2001)), 'description of the step "a"'];
        yield 'route' => [static fn (): StepDefinition => new StepDefinition('a', 'A', route: ''), 'route of the step "a"'];
        yield 'target' => [static fn (): StepDefinition => new StepDefinition('a', 'A', target: str_repeat('a', 256)), 'target of the step "a"'];
        yield 'event without event' => [static fn (): StepDefinition => new StepDefinition('a', 'A', Completion::Event), 'The step "a" names an event exactly when it is done by an event, and a checker exactly when it is done by a checker.'];
        yield 'event on manual' => [static fn (): StepDefinition => new StepDefinition('a', 'A', event: 'E'), 'names an event exactly'];
        yield 'checker without checker' => [static fn (): StepDefinition => new StepDefinition('a', 'A', Completion::Checker), 'names an event exactly'];
        yield 'checker on manual' => [static fn (): StepDefinition => new StepDefinition('a', 'A', checker: 'c'), 'names an event exactly'];
        yield 'empty event' => [static fn (): StepDefinition => new StepDefinition('a', 'A', Completion::Event, event: ''), 'The step "a" names an empty event or checker.'];
        yield 'empty checker' => [static fn (): StepDefinition => new StepDefinition('a', 'A', Completion::Checker, checker: ''), 'names an empty event or checker'];
        yield 'flow identifier' => [static fn (): FlowDefinition => new FlowDefinition('A', 'A', [new StepDefinition('a', 'A')]), 'The flow "A" is not made'];
        yield 'flow title' => [static fn (): FlowDefinition => new FlowDefinition('a', '', [new StepDefinition('a', 'A')]), 'title of the flow "a"'];
        yield 'flow description' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', [new StepDefinition('a', 'A')], ''), 'description of the flow "a"'];
        yield 'translation domain' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', [new StepDefinition('a', 'A')], null, 'a b'), 'The translation domain "a b" of the flow "a" is malformed.'];
        yield 'long translation domain' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', [new StepDefinition('a', 'A')], null, str_repeat('d', 65)), 'is malformed'];
        yield 'domain with new line' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', [new StepDefinition('a', 'A')], null, "d\n"), 'is malformed'];
        yield 'no step' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', []), 'The flow "a" has 0 steps, not one to 100.'];
        yield 'too many steps' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', array_map(static fn (int $i): StepDefinition => new StepDefinition('s'.$i, 'S'), range(0, 100))), 'has 101 steps'];
        yield 'step twice' => [static fn (): FlowDefinition => new FlowDefinition('a', 'A', [new StepDefinition('s', 'S'), new StepDefinition('s', 'T')]), 'The flow "a" declares the step "s" twice.'];
    }

    /**
     * @param Closure(): mixed $declare
     */
    #[DataProvider('refused')]
    public function testADeclarationThatBreaksARuleIsRefused(Closure $declare, string $message): void
    {
        try {
            $declare();
            self::fail('The declaration was accepted.');
        } catch (InvalidFlow $invalidFlow) {
            self::assertInstanceOf(OnboardingException::class, $invalidFlow);
            self::assertStringContainsString($message, $invalidFlow->getMessage());
        }
    }
}
