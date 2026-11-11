<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Toggle;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToggleReason::class)]
#[CoversClass(ToggleRequest::class)]
final class ToggleRequestTest extends TestCase
{
    public function testAReasonIsTrimmed(): void
    {
        self::assertSame('Rollback after errors', ToggleReason::of("  Rollback after errors\n")->value);
    }

    #[TestWith([''])]
    #[TestWith(["  \t\n"])]
    #[TestWith(["\u{00A0}"])]
    #[TestWith(["\u{00A0}\u{2003}\u{3000}\u{2028}\u{202F} "])]
    public function testAnEmptyOrBlankReasonIsRefused(string $reason): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A toggle needs a reason');

        ToggleReason::of($reason);
    }

    public function testUnicodeBlanksAreTrimmedFromAReason(): void
    {
        self::assertSame('Rollback after errors', ToggleReason::of("\u{00A0}Rollback after errors\u{3000}")->value);
        self::assertSame("Rollback\u{00A0}now", ToggleReason::of("Rollback\u{00A0}now")->value, 'an inner blank stays');
    }

    public function testAReasonThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('valid UTF-8');

        ToggleReason::of("Rollback \xC3");
    }

    public function testAReasonIsCappedInCharactersNotBytes(): void
    {
        self::assertSame(1000, mb_strlen(ToggleReason::of(str_repeat('é', 1000))->value));

        $this->expectException(InvalidValue::class);
        ToggleReason::of(str_repeat('é', 1001));
    }

    #[TestWith([''])]
    #[TestWith([' '])]
    #[TestWith(["\u{00A0}"])]
    #[TestWith(["\u{2003}\u{3000}\t"])]
    public function testAnEmptyOrBlankActorIsRefused(string $actor): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A toggle needs an actor');

        new ToggleRequest(FlagName::of('f'), true, $actor, ToggleReason::of('why'));
    }

    public function testAnActorThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('valid UTF-8');

        new ToggleRequest(FlagName::of('f'), true, "operator\xFF", ToggleReason::of('why'));
    }

    public function testAnActorIsKeptAsGiven(): void
    {
        self::assertSame('operator-1', new ToggleRequest(FlagName::of('f'), true, 'operator-1', ToggleReason::of('why'))->actor);
    }
}
