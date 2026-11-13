<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use DateTimeImmutable;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Fixture\Refusals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Activation::class)]
final class ActivationTest extends TestCase
{
    use Refusals;

    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAnActivationStartsFromAPreset(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $activation = Activation::start(Presets::defaults()->get(Presets::SUSPEND_SIGNUPS), Duration::ofHours(2), $at, Actor::of('m-1'), '  Spam wave  ');

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $activation->id);
        self::assertSame('suspend-signups', $activation->preset);
        self::assertSame(['signups'], $activation->restrictionNames());
        self::assertSame($at, $activation->startedAt);
        self::assertSame('2026-11-13 12:00:00', $activation->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('m-1', $activation->startedBy?->id);
        self::assertSame('Spam wave', $activation->reason);
        self::assertNull($activation->liftedAt);
        self::assertNull($activation->liftedBy);
        self::assertNull($activation->liftReason);
        self::assertNotSame($activation->id, Activation::start(Presets::defaults()->get(Presets::READ_ONLY), Duration::ofHours(1), $at, null)->id);
        self::assertNull(Activation::start(Presets::defaults()->get(Presets::READ_ONLY), Duration::ofHours(1), $at, null, '   ')->reason);
        self::assertNull(Activation::start(Presets::defaults()->get(Presets::READ_ONLY), Duration::ofHours(1), $at, null)->reason);
    }

    public function testItIsInForceFromItsStartUntilItsEnd(): void
    {
        $activation = $this->activation();

        self::assertFalse($activation->isInForceAt(new DateTimeImmutable('2026-11-13 09:59:59 UTC')));
        self::assertTrue($activation->isInForceAt(new DateTimeImmutable('2026-11-13 10:00:00 UTC')));
        self::assertTrue($activation->isInForceAt(new DateTimeImmutable('2026-11-13 11:59:59 UTC')));
        self::assertFalse($activation->isInForceAt(new DateTimeImmutable('2026-11-13 12:00:00 UTC')));
        self::assertFalse($activation->isDueAt(new DateTimeImmutable('2026-11-13 11:59:59 UTC')));
        self::assertTrue($activation->isDueAt(new DateTimeImmutable('2026-11-13 12:00:00 UTC')));

        $lifted = $activation->liftedOn(new DateTimeImmutable('2026-11-13 11:00:00 UTC'), Actor::of('m-2'), LiftReason::Manual);

        self::assertFalse($lifted->isInForceAt(new DateTimeImmutable('2026-11-13 11:30:00 UTC')));
        self::assertFalse($lifted->isDueAt(new DateTimeImmutable('2026-11-13 12:30:00 UTC')));
        self::assertSame('m-2', $lifted->liftedBy?->id);
        self::assertSame(LiftReason::Manual, $lifted->liftReason);
        self::assertSame('2026-11-13 11:00:00', $lifted->liftedAt?->format('Y-m-d H:i:s'));
        self::assertSame('Wave', $lifted->reason);
        self::assertSame('m-1', $lifted->startedBy?->id);
        self::assertSame($activation->endsAt, $lifted->endsAt);
    }

    public function testItIsExtended(): void
    {
        $lifted = $this->activation()->liftedOn(new DateTimeImmutable('2026-11-13 11:00:00 UTC'), null, LiftReason::Expired);
        $extended = $lifted->endingAt(new DateTimeImmutable('2026-11-13 13:00:00 UTC'));

        self::assertSame('2026-11-13 13:00:00', $extended->endsAt->format('Y-m-d H:i:s'));
        self::assertSame(self::ID, $extended->id);
        self::assertSame('suspend-signups', $extended->preset);
        self::assertSame(['signups'], $extended->restrictionNames());
        self::assertSame('Wave', $extended->reason);
        self::assertSame('m-1', $extended->startedBy?->id);
        self::assertSame($lifted->liftedAt, $extended->liftedAt);
        self::assertSame(LiftReason::Expired, $extended->liftReason);
        self::assertNull($extended->liftedBy);
    }

    public function testWhatItSuspends(): void
    {
        self::assertTrue($this->activation()->suspends(Restriction::named('signups')));
        self::assertFalse($this->activation()->suspends(Restriction::named('invitations')));
        self::assertTrue($this->activation(['read-only'])->suspends(Restriction::named('my-own-thing')));
        self::assertTrue($this->activation(['uploads', 'read-only'])->suspends(Restriction::named('my-own-thing')));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badActivations(): iterable
    {
        yield 'a bad identifier' => [['id' => 'ABC'], '32 lowercase hexadecimal characters'];
        yield 'an identifier with a newline' => [['id' => self::ID."\n"], '32 lowercase hexadecimal characters'];
        yield 'an identifier with a prefix' => [['id' => 'g'.self::ID], '32 lowercase hexadecimal characters'];
        yield 'a bad preset' => [['preset' => 'Bad'], 'is not a preset name'];
        yield 'nothing suspended' => [['restrictions' => []], 'suspends at least one thing'];
        yield 'an end at the start' => [['endsAt' => new DateTimeImmutable('2026-11-13 10:00:00 UTC')], 'ends after it starts'];
        yield 'a reason too long' => [['reason' => str_repeat('é', 501)], 'at most 500 characters'];
        yield 'a lifting without a reason' => [['liftedAt' => new DateTimeImmutable('2026-11-13 11:00:00 UTC')], 'both the moment and the reason'];
        yield 'a reason without a lifting' => [['liftReason' => LiftReason::Manual], 'both the moment and the reason'];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('badActivations')]
    public function testAnActivationIsRefused(array $change, string $message): void
    {
        $arguments = [
            'id' => self::ID,
            'preset' => 'suspend-signups',
            'restrictions' => [Restriction::named('signups')],
            'startedAt' => new DateTimeImmutable('2026-11-13 10:00:00 UTC'),
            'endsAt' => new DateTimeImmutable('2026-11-13 12:00:00 UTC'),
            'startedBy' => null,
            ...$change,
        ];

        self::assertRefused(InvalidIncident::class, $message, static fn (): mixed => new Activation(...$arguments)); // @phpstan-ignore argument.type
    }

    public function testAReasonAtItsLimitIsKept(): void
    {
        self::assertSame(500, mb_strlen($this->activation(['signups'], str_repeat('é', 500))->reason ?? ''));
    }

    /**
     * @param list<string> $restrictions
     */
    private function activation(array $restrictions = ['signups'], string $reason = 'Wave'): Activation
    {
        return new Activation(
            self::ID,
            'suspend-signups',
            array_map(Restriction::named(...), $restrictions),
            new DateTimeImmutable('2026-11-13 10:00:00 UTC'),
            new DateTimeImmutable('2026-11-13 12:00:00 UTC'),
            Actor::of('m-1'),
            $reason,
        );
    }
}
