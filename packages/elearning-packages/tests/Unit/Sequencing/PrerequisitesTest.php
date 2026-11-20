<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Prerequisite\AtLeast;
use Phil\ElearningPackages\Sequencing\Prerequisite\ItemStatus;
use Phil\ElearningPackages\Sequencing\Prerequisite\Negation;
use Phil\ElearningPackages\Sequencing\Prerequisite\Node;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingBuilder;
use Phil\ElearningPackages\Sequencing\SequencingData;
use Phil\ElearningPackages\Sequencing\SequencingReader;
use Phil\ElearningPackages\Sequencing\SequencingRules;
use Phil\ElearningPackages\Sequencing\SequencingRun;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Prerequisites::class)]
#[CoversClass(Node::class)]
#[CoversClass(ItemStatus::class)]
#[CoversClass(Negation::class)]
#[CoversClass(AtLeast::class)]
#[CoversClass(AttemptResult::class)]
#[CoversClass(SequencingBuilder::class)]
#[CoversClass(SequencingRules::class)]
#[CoversClass(SequencingData::class)]
#[CoversClass(SequencingRun::class)]
#[CoversClass(Sequencer::class)]
final class PrerequisitesTest extends TestCase
{
    private const array STATUSES = [
        'a' => 'passed',
        'b' => 'completed',
        'c' => 'failed',
        'd' => 'incomplete',
        'e' => 'browsed',
        'f' => 'not attempted',
    ];

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function expressions(): iterable
    {
        yield 'passed alone' => ['a', true];
        yield 'completed alone' => ['b', true];
        yield 'failed alone' => ['c', false];
        yield 'browsed alone' => ['e', false];
        yield 'unknown item' => ['zz', false];
        yield 'and' => ['a & b', true];
        yield 'and, one failed' => ['a&c', false];
        yield 'or' => ['c | b', true];
        yield 'or, none' => ['c | d', false];
        yield 'not' => ['~c', true];
        yield 'not not' => ['~ ~ a', true];
        yield 'precedence' => ['c | a & b', true];
        yield 'precedence, and binds first' => ['a | c & d', true];
        yield 'parentheses' => ['(c | a) & d', false];
        yield 'equals' => ['c = "failed"', true];
        yield 'equals, initial' => ['c=f', true];
        yield 'equals, unquoted words' => ['f = not attempted', true];
        yield 'equals, quoted words' => ['f="Not Attempted"', true];
        yield 'equals, other' => ['d = "passed"', false];
        yield 'differs' => ['d <> "passed"', true];
        yield 'differs, same' => ['d<>i', false];
        yield 'browsed' => ['e = b', true];
        yield 'completed initial' => ['b = c', true];
        yield 'passed initial' => ['a = p', true];
        yield 'not attempted initial' => ['zz = n', true];
        yield 'set' => ['2*{a, c, b}', true];
        yield 'set, too few' => ['2*{a,c,d}', false];
        yield 'set of zero' => ['0*{c}', true];
        yield 'set of expressions' => ['1*{c & a, d = i}', true];
        yield 'set and more' => ['3*{a,b,e=b} & ~(c<>f)', true];
        yield 'identifiers with dots and colons' => ['x.y-z:1 | a', true];
    }

    #[DataProvider('expressions')]
    public function testAnExpressionIsEvaluatedOnTheStatusesOfTheItems(string $expression, bool $met): void
    {
        $prerequisites = Prerequisites::parse($expression);

        self::assertSame($met, $prerequisites->isMet(static fn (string $item): string => self::STATUSES[$item] ?? 'not attempted'));
        self::assertSame($expression, $prerequisites->expression);
    }

    public function testTheIdentifiersAreListedOnce(): void
    {
        self::assertSame(['a', 'b', 'c'], Prerequisites::parse('a & (b | ~a) & 2*{c, b, a = p}')->identifiers());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => ['', 'it ends too soon'];
        yield 'blank' => ['   ', 'it ends too soon'];
        yield 'dangling and' => ['a &', 'it ends too soon'];
        yield 'two items' => ['a b', '"b" is unexpected'];
        yield 'unknown character' => ['a ! b', '"!" is not a token'];
        yield 'unclosed parenthesis' => ['(a | b', '")" is expected'];
        yield 'closing first' => [')', '")" is not an item'];
        yield 'set without brace' => ['2*a', '"{" is expected'];
        yield 'set unclosed' => ['2*{a,b', '"}" is expected'];
        yield 'status missing' => ['a =', 'a status is missing'];
        yield 'unknown status' => ['a = "great"', '""great"" is not a status'];
        yield 'not and nothing' => ['a = not', '"not" is not a status'];
        yield 'not UTF-8' => ["a\xFF", 'it is not UTF-8'];
        yield 'too deep' => [str_repeat('(', Prerequisites::MAX_DEPTH).'a'.str_repeat(')', Prerequisites::MAX_DEPTH), 'it nests deeper than 32 levels'];
        yield 'too long' => [str_repeat('a|', 2048).'a', 'A prerequisites expression is longer than 4096 bytes.'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedExpressionIsRefused(string $expression, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        Prerequisites::parse($expression);
    }

    public function testTheMessageShowsAShortPartOfTheExpressionWithoutControlCharacters(): void
    {
        try {
            Prerequisites::parse("a\x01 ".str_repeat('b ', 100));
            self::fail('The expression is refused.');
        } catch (PackageRefused $packageRefused) {
            self::assertStringStartsWith('The prerequisites "a? b', $packageRefused->getMessage());
            self::assertStringEndsWith('cannot be read: "?" is not a token.', $packageRefused->getMessage());
            self::assertLessThan(220, mb_strlen($packageRefused->getMessage()));
        }

        self::assertCount(Prerequisites::MAX_DEPTH - 1, [...$this->depth(Prerequisites::parse(str_repeat('(', Prerequisites::MAX_DEPTH - 1).'a'.str_repeat(')', Prerequisites::MAX_DEPTH - 1)))]);
        self::assertSame(4096, \strlen(Prerequisites::parse(str_repeat('a|', 2047).'aa')->expression));
    }

    public function testScorm12ItemsWaitForTheirPrerequisites(): void
    {
        $session = M::scorm12Session(
            M::item('intro')
            .M::item('quiz', '', '', '<adlcp:prerequisites type="aicc_script">intro</adlcp:prerequisites>')
            .M::item('module', '', M::item('m1').M::item('m2', '', '', '<adlcp:prerequisites>m1 = "failed" | quiz</adlcp:prerequisites>'))
            .M::item('outro', '', '', '<adlcp:prerequisites type="aicc_script">2*{intro, quiz, nowhere}</adlcp:prerequisites>'),
        );

        self::assertSame(PackageFormat::Scorm12, $session->tree->format);
        self::assertTrue($session->tree->isScorm12());
        self::assertSame('intro', $session->tree->get('quiz')->prerequisites?->expression);
        self::assertTrue($session->tree->root->sequencing->controlMode->flow);
        self::assertTrue($session->tree->get('quiz')->sequencing->deliveryControls->objectiveSetByContent);
        self::assertSame('The prerequisites of the activity "quiz" are not met.', $session->navigate(NavigationRequest::Choice, 'quiz')->reason);
        self::assertSame('intro', $session->go(NavigationRequest::Start));
        self::assertSame('incomplete', $session->lessonStatus('intro'));
        self::assertSame('not attempted', $session->lessonStatus('quiz'));
        $session->report(AttemptResult::fromLessonStatus('browsed'));
        self::assertSame('browsed', $session->lessonStatus('intro'));
        self::assertSame('m1', $session->go(NavigationRequest::Continue));
        $session->report(AttemptResult::fromRuntime(RuntimeStatus::fromLessonStatus('failed', 0.2)));
        self::assertSame('failed', $session->lessonStatus('m1'));
        self::assertSame('m2', $session->go(NavigationRequest::Continue));
        $session->report(AttemptResult::fromRuntime(RuntimeStatus::fromLessonStatus('completed')));
        self::assertSame('completed', $session->lessonStatus('module'));
        self::assertSame('waiting', $session->go(NavigationRequest::Exit));
        self::assertSame('completed', $session->lessonStatus('module'));
        self::assertSame('intro', $session->go(NavigationRequest::Choice, 'intro'));
        $session->report(new AttemptResult(completed: true, passed: true, lessonStatus: 'passed'));
        self::assertSame('quiz', $session->go(NavigationRequest::Continue));
        $session->report(AttemptResult::fromRuntime(RuntimeStatus::fromLessonStatus('passed')));
        self::assertSame('outro', $session->go(NavigationRequest::Choice, 'outro'));
        $session->report(AttemptResult::fromRuntime(new RuntimeStatus('completed', 'unknown')));
        self::assertSame('completed', $session->lessonStatus('outro'));
        self::assertSame('sessionEnded', $session->go(NavigationRequest::Continue));
        self::assertSame('passed', $session->lessonStatus('quiz'));
        self::assertNull($session->state->current);
    }

    public function testFlowPassesOverItemsWhosePrerequisitesAreUnmet(): void
    {
        $session = M::scorm12Session(M::item('a').M::item('b', '', '', '<adlcp:prerequisites>a</adlcp:prerequisites>').M::item('c'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(lessonStatus: 'incomplete'));

        self::assertSame('c', $session->go(NavigationRequest::Continue));
        self::assertSame('a', $session->go(NavigationRequest::Previous));
        $session->report(new AttemptResult(lessonStatus: 'completed'));
        self::assertSame('b', $session->go(NavigationRequest::Continue));
    }

    public function testTheStatusOfAnItemReadsFromItsData(): void
    {
        $session = M::scorm12Session(M::item('m', '', M::item('a').M::item('b')));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true));
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(true, false));

        self::assertSame('passed', $session->lessonStatus('a'));
        self::assertSame('failed', $session->lessonStatus('b'));
        self::assertSame('failed', $session->lessonStatus('m'));
        $session->report(new AttemptResult(passed: true));
        self::assertSame('passed', $session->lessonStatus('m'));
        $session->go(NavigationRequest::ExitAll);
        $session->go(NavigationRequest::Start);
        self::assertSame('incomplete', $session->lessonStatus('m'));
        $session->report(new AttemptResult(true));
        self::assertSame('completed', $session->lessonStatus('a'));
        $session->report(new AttemptResult(false));
        self::assertSame('incomplete', $session->lessonStatus('a'));
    }

    public function testALessonStatusIsReadAsWritten(): void
    {
        self::assertEquals(new AttemptResult(true, true, 0.9, null, [], 'passed', true), AttemptResult::fromLessonStatus('passed', 0.9, suspend: true));
        self::assertEquals(new AttemptResult(false, null, null, null, [], 'browsed'), AttemptResult::fromLessonStatus('browsed'));
        self::assertEquals(new AttemptResult(null, null, null, null, [], 'not attempted'), AttemptResult::fromLessonStatus('lost'));
        self::assertEquals(new AttemptResult(true, false, null, Duration::ofCentiseconds(5), [], 'failed', true), AttemptResult::fromRuntime(new RuntimeStatus('completed', 'failed'), Duration::ofCentiseconds(5), true));
        self::assertEquals(new AttemptResult(null, null, 0.5, null, [], 'not attempted'), AttemptResult::fromRuntime(new RuntimeStatus('unknown', 'unknown', 0.5)));

        $session = M::scorm12Session(M::item('a'));
        $session->go(NavigationRequest::Start);
        $this->expectException(SequencingRefused::class);
        $this->expectExceptionMessageIsOrContains('A lesson status is one SCORM 1.2 defines.');
        $session->report(new AttemptResult(lessonStatus: 'lost'));
    }

    public function testPrerequisitesTheReaderCannotUseAreNoted(): void
    {
        $tree = new SequencingReader()->read(M::scorm12(
            M::item('a', '', '', '<adlcp:prerequisites type="script">a</adlcp:prerequisites>')
            .M::item('b', '', '', '<adlcp:prerequisites>a &amp;</adlcp:prerequisites>')
            .M::item('c', '', '', '<adlcp:prerequisites> </adlcp:prerequisites>'),
        ));

        self::assertNull($tree->get('a')->prerequisites);
        self::assertNull($tree->get('b')->prerequisites);
        self::assertNull($tree->get('c')->prerequisites);
        self::assertSame([
            'The prerequisites of the item "a" are of the type "script", which the reader does not know: they are left out.',
            'The item "b": The prerequisites "a &" cannot be read: it ends too soon. They are left out.',
        ], $tree->warnings);
    }

    public function testPrerequisitesDoNotApplyToScorm2004(): void
    {
        $session = M::session(M::item('a').M::item('b', '', '', '<adlcp:prerequisites>a</adlcp:prerequisites>'));

        self::assertNull($session->tree->get('b')->prerequisites);
        self::assertSame('b', $session->go(NavigationRequest::Choice, 'b'));
    }

    public function testTheLessonStatusOfAnUnknownActivityIsRefused(): void
    {
        $session = M::session(M::item('a'));

        $this->expectException(SequencingRefused::class);
        $session->lessonStatus('none');
    }

    /**
     * @return iterable<int, Node>
     */
    private function depth(Prerequisites $prerequisites): iterable
    {
        $root = new ReflectionProperty(Prerequisites::class, 'root')->getValue($prerequisites);
        self::assertInstanceOf(ItemStatus::class, $root);

        yield from array_fill(0, Prerequisites::MAX_DEPTH - 1, $root);
    }
}
