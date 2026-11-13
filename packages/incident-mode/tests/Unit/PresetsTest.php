<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\Exception\UnknownPreset;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Fixture\Refusals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preset::class)]
#[CoversClass(Presets::class)]
#[CoversClass(UnknownPreset::class)]
final class PresetsTest extends TestCase
{
    use Refusals;

    public function testAPresetHoldsItsRestrictionsOnceEach(): void
    {
        $preset = Preset::of('quiet', '  Quiet  ', ['new-posts', 'new-comments', 'new-posts'], Duration::ofHours(1), Duration::ofHours(1));

        self::assertSame('quiet', $preset->name);
        self::assertSame('Quiet', $preset->label);
        self::assertSame(['new-posts', 'new-comments'], $preset->restrictionNames());
        self::assertSame(3_600, $preset->defaultDuration->seconds);
        self::assertSame(3_600, $preset->maximumDuration->seconds);
        self::assertSame([0, 1], array_keys($preset->restrictions));
        self::assertSame(str_repeat('é', 120), Preset::of('long', str_repeat('é', 120), ['uploads'], Duration::ofHours(1), Duration::ofHours(2))->label);
    }

    /**
     * @return iterable<string, array{string, string, list<string>, Duration, Duration, string}>
     */
    public static function badPresets(): iterable
    {
        yield 'a bad name' => ['Quiet', 'Quiet', ['uploads'], Duration::ofHours(1), Duration::ofHours(2), 'is not a preset name'];
        yield 'a blank label' => ['quiet', '   ', ['uploads'], Duration::ofHours(1), Duration::ofHours(2), 'has 1 to 120 characters'];
        yield 'a label too long' => ['quiet', str_repeat('é', 121), ['uploads'], Duration::ofHours(1), Duration::ofHours(2), 'has 1 to 120 characters'];
        yield 'nothing suspended' => ['quiet', 'Quiet', [], Duration::ofHours(1), Duration::ofHours(2), 'suspends at least one thing'];
        yield 'a bad restriction' => ['quiet', 'Quiet', ['New posts'], Duration::ofHours(1), Duration::ofHours(2), 'is not a restriction name'];
        yield 'a default longer than the longest' => ['quiet', 'Quiet', ['uploads'], Duration::ofHours(3), Duration::ofHours(2), 'proposes a duration longer than the longest'];
    }

    /**
     * @param list<string> $restrictions
     */
    #[DataProvider('badPresets')]
    public function testAPresetIsRefused(string $name, string $label, array $restrictions, Duration $default, Duration $maximum, string $message): void
    {
        self::assertRefused(InvalidIncident::class, $message, static fn (): mixed => Preset::of($name, $label, $restrictions, $default, $maximum));
    }

    public function testTheBuiltInPresets(): void
    {
        $presets = Presets::defaults();

        self::assertSame(
            [Presets::SUSPEND_SIGNUPS, Presets::SUSPEND_INVITATIONS, Presets::SUSPEND_NEW_MESSAGES, Presets::SUSPEND_CONTRIBUTIONS, Presets::READ_ONLY],
            array_map(static fn (Preset $preset): string => $preset->name, $presets->all()),
        );
        self::assertSame([Restriction::SIGNUPS], $presets->get(Presets::SUSPEND_SIGNUPS)->restrictionNames());
        self::assertSame([Restriction::INVITATIONS], $presets->get(Presets::SUSPEND_INVITATIONS)->restrictionNames());
        self::assertSame([Restriction::NEW_MESSAGES], $presets->get(Presets::SUSPEND_NEW_MESSAGES)->restrictionNames());
        self::assertSame([Restriction::READ_ONLY], $presets->get(Presets::READ_ONLY)->restrictionNames());
        self::assertSame(
            ['signups', 'invitations', 'new-messages', 'new-posts', 'new-comments', 'uploads'],
            $presets->get(Presets::SUSPEND_CONTRIBUTIONS)->restrictionNames(),
        );
        self::assertSame(1_800, $presets->get(Presets::READ_ONLY)->defaultDuration->seconds);
        self::assertSame(172_800, $presets->get(Presets::READ_ONLY)->maximumDuration->seconds);
        self::assertSame(7_200, $presets->get(Presets::SUSPEND_SIGNUPS)->defaultDuration->seconds);
        self::assertSame(604_800, $presets->get(Presets::SUSPEND_INVITATIONS)->maximumDuration->seconds);
        self::assertSame(3_600, $presets->get(Presets::SUSPEND_NEW_MESSAGES)->defaultDuration->seconds);
        self::assertSame(259_200, $presets->get(Presets::SUSPEND_CONTRIBUTIONS)->maximumDuration->seconds);
        self::assertSame('Suspend sign-ups', $presets->get(Presets::SUSPEND_SIGNUPS)->label);
        self::assertSame(604_800, $presets->get(Presets::SUSPEND_SIGNUPS)->maximumDuration->seconds);
        self::assertSame(7_200, $presets->get(Presets::SUSPEND_INVITATIONS)->defaultDuration->seconds);
        self::assertSame(259_200, $presets->get(Presets::SUSPEND_NEW_MESSAGES)->maximumDuration->seconds);
        self::assertSame(3_600, $presets->get(Presets::SUSPEND_CONTRIBUTIONS)->defaultDuration->seconds);
        self::assertCount(5, Presets::builtIn());
    }

    public function testTheApplicationAddsReplacesAndLeavesOut(): void
    {
        $quiet = Preset::of('quiet', 'Quiet', ['new-posts'], Duration::ofHours(1), Duration::ofHours(4));
        $signups = Preset::of(Presets::SUSPEND_SIGNUPS, 'No new accounts', ['signups'], Duration::ofHours(1), Duration::ofDays(1));
        $presets = Presets::withDefaults([$quiet, $signups], [Presets::READ_ONLY]);

        self::assertSame(
            [Presets::SUSPEND_SIGNUPS, Presets::SUSPEND_INVITATIONS, Presets::SUSPEND_NEW_MESSAGES, Presets::SUSPEND_CONTRIBUTIONS, 'quiet'],
            array_map(static fn (Preset $preset): string => $preset->name, $presets->all()),
        );
        self::assertSame($signups, $presets->get(Presets::SUSPEND_SIGNUPS));
        self::assertTrue($presets->has('quiet'));
        self::assertFalse($presets->has(Presets::READ_ONLY));
        self::assertCount(5, Presets::withDefaults([])->all());
    }

    public function testAnUnknownPresetIsRefused(): void
    {
        $this->expectExceptionObject(UnknownPreset::named('storm'));

        Presets::defaults()->get('storm');
    }
}
