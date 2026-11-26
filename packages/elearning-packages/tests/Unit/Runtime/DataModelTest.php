<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Runtime\Access;
use Phil\ElearningPackages\Runtime\DataModel;
use Phil\ElearningPackages\Runtime\ElementDefinition;
use Phil\ElearningPackages\Runtime\ElementName;
use Phil\ElearningPackages\Runtime\Mistake;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\ValueCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataModel::class)]
#[CoversClass(ElementDefinition::class)]
#[CoversClass(ElementName::class)]
#[CoversClass(ScormError::class)]
#[CoversClass(Mistake::class)]
#[CoversClass(ValueCheck::class)]
final class DataModelTest extends TestCase
{
    public function testEachVersionDefinesItsElements(): void
    {
        self::assertCount(38, DataModel::definitions(ScormVersion::Scorm12));
        self::assertCount(56, DataModel::definitions(ScormVersion::Scorm2004));
        self::assertSame(Access::WriteOnly, DataModel::definition(ScormVersion::Scorm12, 'cmi.interactions.n.id')?->access);
        self::assertSame(Access::ReadWrite, DataModel::definition(ScormVersion::Scorm2004, 'cmi.interactions.n.id')?->access);
        self::assertNull(DataModel::definition(ScormVersion::Scorm2004, 'cmi.core.lesson_status'));
        self::assertSame(['cmi.objectives' => 100, 'cmi.interactions' => 250, 'cmi.interactions.n.objectives' => 10, 'cmi.interactions.n.correct_responses' => 10], DataModel::collections(ScormVersion::Scorm12));
        self::assertTrue(DataModel::isParent(ScormVersion::Scorm12, 'cmi.core'));
        self::assertTrue(DataModel::isParent(ScormVersion::Scorm2004, 'cmi.interactions.n'));
        self::assertFalse(DataModel::isParent(ScormVersion::Scorm2004, 'cmi.location'));
        self::assertFalse(DataModel::isParent(ScormVersion::Scorm2004, 'cmi.loc'));
        self::assertNull(DataModel::children(ScormVersion::Scorm2004, 'cmi'));
        self::assertSame('raw,min,max', DataModel::children(ScormVersion::Scorm12, 'cmi.objectives.n.score'));
        self::assertSame(10, DataModel::correctResponses(ScormVersion::Scorm12, 'true-false'));
        self::assertSame(1, DataModel::correctResponses(ScormVersion::Scorm2004, 'true-false'));
        self::assertSame(10, DataModel::correctResponses(ScormVersion::Scorm2004, 'choice'));
        self::assertSame(1, DataModel::correctResponses(ScormVersion::Scorm2004, ''));
        self::assertNull(DataModel::responsePattern('other', true));
        self::assertNull(DataModel::responsePattern('', false));
    }

    public function testEveryPatternReadsInPhpAsInJavaScript(): void
    {
        foreach ([ScormVersion::Scorm12, ScormVersion::Scorm2004] as $version) {
            foreach (DataModel::definitions($version) as $name => $definition) {
                self::assertSame($name, $definition->name);

                if (null !== $definition->pattern) {
                    self::assertStringStartsWith('^', $definition->pattern);
                    self::assertStringEndsWith('$', $definition->pattern);
                    self::assertNotFalse(@preg_match('/'.$definition->pattern.'/u', ''), $name);
                    // No escape JavaScript's `u` flag refuses: only syntax characters and classes are escaped.
                    self::assertDoesNotMatchRegularExpression('/\\\\[^dDsSwWbB^$\\\\.*+?()[\]{}|\/-]/', $definition->pattern, $name);
                }
            }
        }
    }

    public function testTheDescriptionHandsTheBrowserTheRules(): void
    {
        $description = DataModel::describe(ScormVersion::Scorm2004);

        self::assertSame('2004', $description['version']);
        self::assertSame('1.0', $description['dataModelVersion']);
        self::assertSame(['access' => 'rw', 'maxChars' => 1000], $description['elements']['cmi.location']);
        self::assertSame(['access' => 'rw', 'pattern' => '^'.DataModel::DECIMAL.'$', 'maxChars' => 40, 'min' => -1.0, 'max' => 1.0], $description['elements']['cmi.score.scaled']);
        self::assertSame(['access' => 'rw', 'maxChars' => 4000, 'check' => 'interaction-response'], $description['elements']['cmi.interactions.n.learner_response']);
        self::assertSame(['access' => 'ro', 'pattern' => '^(?:credit|no-credit)$', 'default' => 'credit'], $description['elements']['cmi.credit']);
        self::assertSame(['access' => 'rw', 'pattern' => '^'.DataModel::DECIMAL.'$', 'maxChars' => 40, 'min' => 0.0, 'default' => '1'], $description['elements']['cmi.learner_preference.audio_level']);
        self::assertSame(10, $description['correctResponses']['choice']);
        self::assertSame(['pattern' => '^(?:true|false)$', 'response' => '^(?:true|false)$'], $description['responses']['true-false']);
        self::assertSame(['pattern' => null, 'response' => null], $description['responses']['other']);
        self::assertContains('cmi.score', $description['parents']);
        self::assertSame(250, $description['collections']['cmi.interactions']);
        self::assertSame(ScormError::codes(ScormVersion::Scorm2004), $description['errors']);

        $twelve = DataModel::describe(ScormVersion::Scorm12);

        self::assertSame([], $twelve['correctResponses']);
        self::assertSame([], $twelve['responses']);
        self::assertSame(['access' => 'wo', 'pattern' => '^(?:time-out|suspend|logout|)$'], $twelve['elements']['cmi.core.exit']);
    }

    /**
     * @return iterable<string, array{Mistake, int, int}>
     */
    public static function mistakes(): iterable
    {
        yield 'undefined' => [Mistake::Undefined, 401, 401];
        yield 'unimplemented' => [Mistake::Unimplemented, 401, 402];
        yield 'not initialized' => [Mistake::NotInitialized, 0, 403];
        yield 'read only' => [Mistake::ReadOnly, 403, 404];
        yield 'write only' => [Mistake::WriteOnly, 404, 405];
        yield 'keyword' => [Mistake::Keyword, 402, 404];
        yield 'type' => [Mistake::TypeMismatch, 405, 406];
        yield 'range' => [Mistake::OutOfRange, 405, 407];
        yield 'dependency' => [Mistake::Dependency, 405, 408];
        yield 'order' => [Mistake::OutOfOrder, 201, 351];
        yield 'get out of range' => [Mistake::GetOutOfRange, 201, 301];
        yield 'no children' => [Mistake::NoChildren, 202, 301];
        yield 'no count' => [Mistake::NoCount, 203, 301];
        yield 'set failed' => [Mistake::SetFailed, 101, 351];
        yield 'get failed' => [Mistake::GetFailed, 101, 301];
    }

    #[DataProvider('mistakes')]
    public function testEachMistakeHasItsNumberInEachVersion(Mistake $mistake, int $twelve, int $twothousandfour): void
    {
        self::assertSame($twelve, ScormError::for(ScormVersion::Scorm12, $mistake));
        self::assertSame($twothousandfour, ScormError::for(ScormVersion::Scorm2004, $mistake));
    }

    public function testEachCodeHasItsMessage(): void
    {
        self::assertSame([0, 101, 201, 202, 203, 301, 401, 402, 403, 404, 405], array_keys(ScormError::codes(ScormVersion::Scorm12)));
        self::assertSame([0, 101, 102, 103, 104, 111, 112, 113, 122, 123, 132, 133, 142, 143, 201, 301, 351, 391, 401, 402, 403, 404, 405, 406, 407, 408], array_keys(ScormError::codes(ScormVersion::Scorm2004)));
        self::assertSame('Incorrect data type', ScormError::message(ScormVersion::Scorm12, 405));
        self::assertSame('Data Model Dependency Not Established', ScormError::message(ScormVersion::Scorm2004, 408));
        self::assertSame('', ScormError::message(ScormVersion::Scorm12, 408));
    }

    /**
     * @return iterable<string, array{string, ?string, list<int>, ?string, ?string}>
     */
    public static function names(): iterable
    {
        yield 'an element' => ['cmi.core.lesson_status', 'cmi.core.lesson_status', [], null, null];
        yield 'an index' => ['cmi.interactions.12.objectives.3.id', 'cmi.interactions.n.objectives.n.id', [12, 3], null, null];
        yield 'a keyword' => ['cmi.interactions.0.objectives._count', 'cmi.interactions.n.objectives', [0], '_count', null];
        yield 'a target' => ['adl.nav.request_valid.jump.{target=a.b}', 'adl.nav.request_valid.jump', [], null, '{target=a.b}'];
        yield 'an index of four digits' => ['cmi.objectives.9999.id', 'cmi.objectives.n.id', [9999], null, null];
        yield 'an index of five digits' => ['cmi.objectives.10000.id', null, [], null, null];
        yield 'a target with a space' => ['adl.nav.request_valid.choice.{target=a b}', null, [], null, null];
        yield 'too long' => [str_repeat('a', 256), null, [], null, null];
        yield 'the longest' => [str_repeat('a', 41).'.'.str_repeat('b', 41).'.'.str_repeat('c', 41).'.'.str_repeat('d', 41).'.'.str_repeat('e', 41).'.'.str_repeat('f', 41), str_repeat('a', 41).'.'.str_repeat('b', 41).'.'.str_repeat('c', 41).'.'.str_repeat('d', 41).'.'.str_repeat('e', 41).'.'.str_repeat('f', 41), [], null, null];
        yield 'a long segment' => [str_repeat('a', 42), null, [], null, null];
        yield 'a digit first' => ['cmi.1core', null, [], null, null];
        yield 'the index of a pattern' => ['cmi.interactions.n.learner_response', null, [], null, null];
    }

    /**
     * @param list<int> $indexes
     */
    #[DataProvider('names')]
    public function testANameIsReadIntoItsPatternAndIndexes(string $name, ?string $pattern, array $indexes, ?string $keyword, ?string $target): void
    {
        $parsed = ElementName::parse($name);

        self::assertSame($pattern, $parsed?->pattern);

        if ($parsed instanceof ElementName) {
            self::assertSame([$name, $indexes, $keyword, $target], [$parsed->name, $parsed->indexes, $parsed->keyword, $parsed->target]);
        }
    }

    public function testANameGivesItsCollectionsAndSiblings(): void
    {
        $name = ElementName::parse('cmi.interactions.3.objectives.1.id');

        self::assertNotNull($name);
        self::assertSame('cmi.interactions', $name->collection(0));
        self::assertSame('cmi.interactions.3.objectives', $name->collection(1));
        self::assertSame('cmi.interactions', $name->collectionPattern(0));
        self::assertSame('cmi.interactions.n.objectives', $name->collectionPattern(1));
        self::assertSame('cmi.interactions.3.type', $name->sibling(0, 'type'));
        self::assertSame('cmi.interactions.3.objectives.1.x', $name->sibling(1, 'x'));
    }
}
