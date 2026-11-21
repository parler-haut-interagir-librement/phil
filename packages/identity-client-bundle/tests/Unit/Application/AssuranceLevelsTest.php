<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Override;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssuranceLevels::class)]
#[CoversClass(AssuranceLevel::class)]
final class AssuranceLevelsTest extends TestCase
{
    private AssuranceLevels $levels;

    private AssuranceLevel $basic;

    private AssuranceLevel $strong;

    private AssuranceLevel $strongAndFresh;

    private AssuranceLevel $fresh;

    #[Override]
    protected function setUp(): void
    {
        $this->basic = new AssuranceLevel('basic', 'a');
        $this->strong = new AssuranceLevel('strong', 'b');
        $this->strongAndFresh = new AssuranceLevel('strong_fresh', 'b', 60);
        $this->fresh = new AssuranceLevel('fresh', null, 60);
        $this->levels = new AssuranceLevels([$this->basic, $this->strong, $this->strongAndFresh, $this->fresh, new AssuranceLevel('top', 'c')]);
    }

    public function testALevelIsFoundByName(): void
    {
        self::assertSame($this->strong, $this->levels->get('strong'));
        self::assertNull($this->levels->get('unknown'));
    }

    public function testAClassReachesItsOwnLevelAndTheWeakerOnes(): void
    {
        self::assertTrue($this->levels->reaches($this->strong, 'b'));
        self::assertTrue($this->levels->reaches($this->basic, 'b'));
        self::assertTrue($this->levels->reaches($this->strong, 'c'));
        self::assertFalse($this->levels->reaches($this->strong, 'a'));
    }

    public function testAnUnknownOrAbsentClassReachesNothing(): void
    {
        self::assertFalse($this->levels->reaches($this->basic, 'other'));
        self::assertFalse($this->levels->reaches($this->basic, null));
    }

    public function testALevelWithoutAClassIsReachedByAnyAuthentication(): void
    {
        self::assertTrue($this->levels->reaches($this->fresh, null));
    }

    public function testTwoLevelsCanShareAClass(): void
    {
        self::assertTrue($this->levels->reaches($this->strongAndFresh, 'b'));
        self::assertSame(['b', 'c'], $this->levels->acceptableClasses($this->strongAndFresh));
    }

    public function testTheAcceptableClassesAreTheLevelsAndTheStrongerOnes(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->levels->acceptableClasses($this->basic));
        self::assertSame(['b', 'c'], $this->levels->acceptableClasses($this->strong));
        self::assertSame([], $this->levels->acceptableClasses($this->fresh));
    }

    public function testWithoutARequiredLevelAnyAuthenticationWillDo(): void
    {
        self::assertNull($this->levels->required());
        self::assertTrue($this->levels->reachesRequired(null));
        self::assertSame([], $this->levels->requestedClasses(null));
        self::assertSame(['b', 'c'], $this->levels->requestedClasses($this->strong));
        self::assertSame([], $this->levels->requestedClasses($this->fresh));
    }

    public function testTheRequiredLevelIsReachedByItsClassOrAStrongerOne(): void
    {
        $levels = $this->requiring('strong');

        self::assertSame($this->strong, $levels->required());
        self::assertTrue($levels->reachesRequired('b'));
        self::assertTrue($levels->reachesRequired('c'));
        self::assertFalse($levels->reachesRequired('a'));
        self::assertFalse($levels->reachesRequired('other'));
        self::assertFalse($levels->reachesRequired(null));
    }

    public function testASignInAsksForTheRequiredClasses(): void
    {
        self::assertSame(['b', 'c'], $this->requiring('strong')->requestedClasses(null));
    }

    public function testALevelWithoutClassAsksForTheRequiredOnes(): void
    {
        self::assertSame(['b', 'c'], $this->requiring('strong')->requestedClasses($this->fresh));
    }

    public function testAWeakerLevelIsNarrowedToTheRequiredClasses(): void
    {
        self::assertSame(['b', 'c'], $this->requiring('strong')->requestedClasses($this->basic));
    }

    public function testAStrongerLevelKeepsItsOwnClasses(): void
    {
        self::assertSame(['c'], $this->requiring('strong')->requestedClasses(new AssuranceLevel('top', 'c')));
        self::assertSame(['b', 'c'], $this->requiring('strong')->requestedClasses($this->strongAndFresh));
    }

    public function testAnUnknownRequiredLevelIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The required assurance level "unknown" must be a level of the scale with an acr.');

        $this->requiring('unknown');
    }

    public function testARequiredLevelWithoutClassIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->requiring('fresh');
    }

    private function requiring(string $level): AssuranceLevels
    {
        return new AssuranceLevels([$this->basic, $this->strong, $this->strongAndFresh, $this->fresh, new AssuranceLevel('top', 'c')], $level);
    }
}
