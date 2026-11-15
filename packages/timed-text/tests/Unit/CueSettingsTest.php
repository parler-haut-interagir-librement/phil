<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\CueSettings;
use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CueSettings::class)]
#[CoversClass(Region::class)]
final class CueSettingsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function settings(): iterable
    {
        yield 'vertical rl' => ['vertical', 'rl', true];
        yield 'vertical lr' => ['vertical', 'lr', true];
        yield 'vertical other' => ['vertical', 'tb', false];
        yield 'line number' => ['line', '-3', true];
        yield 'line percent' => ['line', '85.5%', true];
        yield 'line hundred' => ['line', '100%', true];
        yield 'line hundred point zero' => ['line', '100.00%', true];
        yield 'line past hundred' => ['line', '101%', false];
        yield 'line auto' => ['line', 'auto', true];
        yield 'line alignment' => ['line', '10%,end', true];
        yield 'line bad alignment' => ['line', '10%,middle', false];
        yield 'line trailing text' => ['line', '10%x', false];
        yield 'position' => ['position', '50%,line-left', true];
        yield 'position auto' => ['position', 'auto', true];
        yield 'position number' => ['position', '50', false];
        yield 'size' => ['size', '80%', true];
        yield 'size alignment' => ['size', '80%,start', false];
        yield 'align' => ['align', 'right', true];
        yield 'align other' => ['align', 'justify', false];
        yield 'region' => ['region', 'speakers', true];
        yield 'region with an arrow' => ['region', 'a-->b', false];
        yield 'region empty' => ['region', '', false];
        yield 'unknown' => ['colour', 'red', false];
    }

    #[DataProvider('settings')]
    public function testItKnowsTheWebVttSettings(string $name, string $value, bool $valid): void
    {
        self::assertSame($valid, CueSettings::isValid($name, $value));
    }

    public function testItReadsTheSettingsOfATimingLine(): void
    {
        [$settings, $rejected] = CueSettings::parse(" align:start\tline:10%  colour:red line:20% size nonsense:");

        self::assertSame(['align' => 'start', 'line' => '20%'], $settings->values);
        self::assertSame(['colour:red', 'size', 'nonsense:'], $rejected);
        self::assertSame('align:start line:20%', $settings->toString());
        self::assertSame('20%', $settings->get('line'));
        self::assertNull($settings->get('size'));
        self::assertFalse($settings->isEmpty());
    }

    public function testARepeatedSettingMovesToItsLastPlace(): void
    {
        [$settings] = CueSettings::parse('line:10% align:start line:20%');

        self::assertSame('align:start line:20%', $settings->toString());
    }

    public function testItReadsNothingFromNothing(): void
    {
        [$settings, $rejected] = CueSettings::parse('');

        self::assertTrue($settings->isEmpty());
        self::assertSame([], $rejected);
        self::assertSame('', $settings->toString());
    }

    public function testItRefusesASettingBuiltByHand(): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('"align:justify" is not a WebVTT cue setting.');

        new CueSettings(['align' => 'justify']);
    }

    public function testItReadsARegion(): void
    {
        [$region, $rejected] = Region::parse(['id:speakers width:40%', 'lines:3 colour:red', 'scroll:up width:50%']);

        self::assertNotNull($region);
        self::assertSame('speakers', $region->id);
        self::assertSame(['lines' => '3', 'scroll' => 'up', 'width' => '50%'], $region->settings);
        self::assertSame(['colour:red'], $rejected);
        self::assertSame(['id:speakers', 'lines:3', 'scroll:up', 'width:50%'], $region->lines());
    }

    public function testARegionNeedsAnIdentifier(): void
    {
        self::assertSame([null, ['id:a-->b']], Region::parse(['width:40%', 'id:a-->b']));
        self::assertSame([null, []], Region::parse([]));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function regionSettings(): iterable
    {
        yield 'width' => ['width', '40%', true];
        yield 'width without percent' => ['width', '40', false];
        yield 'lines' => ['lines', '3', true];
        yield 'lines percent' => ['lines', '3%', false];
        yield 'region anchor' => ['regionanchor', '0%,100%', true];
        yield 'viewport anchor' => ['viewportanchor', '10%,90%', true];
        yield 'anchor with one value' => ['viewportanchor', '10%', false];
        yield 'scroll' => ['scroll', 'up', true];
        yield 'scroll down' => ['scroll', 'down', false];
        yield 'unknown' => ['id', 'x', false];
    }

    #[DataProvider('regionSettings')]
    public function testItKnowsTheRegionSettings(string $name, string $value, bool $valid): void
    {
        self::assertSame($valid, Region::isValid($name, $value));
    }

    public function testItRefusesARegionBuiltByHand(): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('"scroll:down" is not a WebVTT region setting.');

        new Region('a', ['scroll' => 'down']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['a b'];
        yield 'arrow' => ['a-->'];
        yield 'line feed' => ["a\n"];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testItRefusesAnIdentifierThatIsNotOneToken(string $id): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a region is one token, without "-->".');

        new Region($id);
    }
}
