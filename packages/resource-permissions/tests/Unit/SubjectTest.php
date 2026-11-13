<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subject::class)]
final class SubjectTest extends TestCase
{
    public function testOneObject(): void
    {
        $subject = Subject::of(ResourceReference::of('member', 'ann'));

        self::assertSame('member:ann', $subject->toString());
        self::assertFalse($subject->isSet());
        self::assertFalse($subject->isEvery());
        self::assertNull($subject->relation);
    }

    public function testASet(): void
    {
        $subject = Subject::set(ResourceReference::of('group', 'g'), 'member');

        self::assertSame('group:g#member', $subject->toString());
        self::assertTrue($subject->isSet());
        self::assertFalse($subject->isEvery());
        self::assertSame('member', $subject->relation);
    }

    public function testEveryObjectOfAKind(): void
    {
        $subject = Subject::every('member');

        self::assertSame('member:*', $subject->toString());
        self::assertTrue($subject->isEvery());
        self::assertFalse($subject->isSet());
    }

    #[DataProvider('written')]
    public function testFromStringReadsWhatToStringWrote(string $written): void
    {
        self::assertSame($written, Subject::fromString($written)->toString());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function written(): iterable
    {
        yield ['member:ann'];
        yield ['member:*'];
        yield ['group:g#member'];
        yield ['doc:a:b:c'];
        yield ['group:a:b#member'];
    }

    #[DataProvider('refused')]
    public function testFromStringRefusesWhatIsNotASubject(string $refused): void
    {
        $this->expectException(InvalidRelation::class);

        Subject::fromString($refused);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function refused(): iterable
    {
        yield ['member'];
        yield ['Member:ann'];
        yield ['member:'];
        yield ['group:g#'];
        yield ['group:g#Member'];
        yield ['group:*#member'];
        yield ['group:a#b#member'];
        yield ['*:x'];
    }

    public function testAnIdentifierHoldsNoHash(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('A subject identifier holds no "#".');

        Subject::of(ResourceReference::of('member', 'a#b'));
    }

    public function testAnIdentifierIsNeverAStarAlone(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('Subject::every()');

        Subject::of(ResourceReference::of('member', '*'));
    }

    public function testASetIsNeverOfEveryObject(): void
    {
        $this->expectException(InvalidRelation::class);

        Subject::set(ResourceReference::of('group', '*'), 'member');
    }

    public function testASetsRelationFollowsTheRules(): void
    {
        $this->expectException(InvalidRelation::class);

        Subject::set(ResourceReference::of('group', 'g'), '9');
    }

    public function testEveryRefusesAKindAgainstTheRules(): void
    {
        $this->expectException(InvalidRelation::class);

        Subject::every('Member');
    }

    public function testAStarInsideAnIdentifierIsAnIdentifier(): void
    {
        self::assertFalse(Subject::of(ResourceReference::of('member', 'a*'))->isEvery());
    }

    public function testReaches(): void
    {
        $ann = Subject::fromString('member:ann');
        $every = Subject::every('member');
        $set = Subject::fromString('group:g#member');

        self::assertTrue($ann->reaches(Subject::fromString('member:ann')));
        self::assertFalse($ann->reaches(Subject::fromString('member:bob')));
        self::assertTrue($every->reaches($ann));
        self::assertTrue($every->reaches(Subject::every('member')));
        self::assertFalse($every->reaches(Subject::fromString('visitor:ann')));
        self::assertFalse($every->reaches(Subject::fromString('member:g#member')));
        self::assertFalse($ann->reaches($every));
        self::assertTrue($set->reaches(Subject::fromString('group:g#member')));
        self::assertFalse($set->reaches(Subject::fromString('group:g#owner')));
        self::assertFalse(Subject::fromString('group:g')->reaches($set));
    }

    public function testEquals(): void
    {
        self::assertTrue(Subject::fromString('group:g#member')->equals(Subject::fromString('group:g#member')));
        self::assertFalse(Subject::fromString('group:g#member')->equals(Subject::fromString('group:g')));
        self::assertFalse(Subject::fromString('group:g')->equals(Subject::fromString('group:h')));
    }
}
