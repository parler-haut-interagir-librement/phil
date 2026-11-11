<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Edit;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Exception\EditConflict;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditRequest::class)]
#[CoversClass(RevisedDefinition::class)]
#[CoversClass(EditConflict::class)]
final class EditRequestTest extends TestCase
{
    public function testACreationNamesNoRevision(): void
    {
        $request = new EditRequest($this->definition(), 'operator', ToggleReason::of('Opening the beta.'));

        self::assertTrue($request->isCreation());
        self::assertSame('beta', $request->flag()->value);
    }

    public function testAnEditNamesItsRevision(): void
    {
        $request = new EditRequest($this->definition(), 'operator', ToggleReason::of('Widening.'), 1);

        self::assertFalse($request->isCreation());
        self::assertSame(1, $request->revision);
    }

    #[TestWith([''])]
    #[TestWith([' '])]
    #[TestWith(["\u{00A0}"])]
    public function testAnEditNeedsAnActor(string $actor): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An edit needs an actor; an anonymous edit cannot be audited.');

        new EditRequest($this->definition(), $actor, ToggleReason::of('Why.'));
    }

    public function testTheActorMustBeValidUtf8(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The actor of an edit must be valid UTF-8.');

        new EditRequest($this->definition(), "\xC3", ToggleReason::of('Why.'));
    }

    public function testARevisionIsAtLeastOne(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A revision is at least 1; got 0.');

        new EditRequest($this->definition(), 'operator', ToggleReason::of('Why.'), 0);
    }

    public function testAStoredRevisionIsAtLeastOne(): void
    {
        self::assertSame(1, new RevisedDefinition($this->definition(), 1)->revision);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A revision is at least 1; got 0.');

        new RevisedDefinition($this->definition(), 0);
    }

    public function testTheConflictsSayWhatHappened(): void
    {
        self::assertSame('The flag "beta" changed since revision 3; read it again before editing it.', EditConflict::staleRevision(FlagName::of('beta'), 3)->getMessage());
        self::assertSame('A flag named "beta" is already declared.', EditConflict::alreadyDeclared(FlagName::of('beta'))->getMessage());
    }

    private function definition(): FlagDefinition
    {
        return new FlagDefinition(FlagName::of('beta'), false);
    }
}
