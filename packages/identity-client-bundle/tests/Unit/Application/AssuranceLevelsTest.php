<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Application;

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
}
