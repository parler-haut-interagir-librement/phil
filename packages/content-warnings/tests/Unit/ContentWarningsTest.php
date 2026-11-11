<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Exception\InvalidWarning;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Phil\ContentWarnings\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentWarning::class)]
#[CoversClass(ContentWarnings::class)]
#[CoversClass(InvalidWarning::class)]
#[CoversClass(UnreadableWarnings::class)]
final class ContentWarningsTest extends TestCase
{
    public function testAWarningHasAThemeAnOptionalSeverityAndATrimmedNote(): void
    {
        $warning = new ContentWarning('gore', Severity::Severe, '  The final battle  ');

        self::assertSame(['gore', Severity::Severe, 'The final battle'], [$warning->theme, $warning->severity, $warning->note]);
        self::assertNull(new ContentWarning('gore', note: '   ')->note);
        self::assertNull(new ContentWarning('gore')->note);
        self::assertNull(new ContentWarning('gore')->severity);
        self::assertSame(500, mb_strlen((string) new ContentWarning('gore', note: str_repeat('é', 500))->note));
    }

    public function testAMalformedThemeIsRefused(): void
    {
        $this->expectException(InvalidWarning::class);
        $this->expectExceptionMessageIsOrContains('Invalid content warning: "'.str_repeat('É', 80).'" is not a theme key.');

        new ContentWarning(str_repeat('É', 81));
    }

    public function testANoteOverFiveHundredCharactersIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('the note of "gore" is over 500 characters');

        new ContentWarning('gore', note: str_repeat('é', 501));
    }

    public function testWarningsAreOnePerThemeInOrder(): void
    {
        $warnings = ContentWarnings::of(new ContentWarning('spiders'), new ContentWarning('gore', Severity::Mild));

        self::assertSame(['spiders', 'gore'], $warnings->themes());
        self::assertCount(2, $warnings);
        self::assertSame(Severity::Mild, $warnings->get('gore')?->severity);
        self::assertNull($warnings->get('clowns'));
        self::assertTrue($warnings->has('gore'));
        self::assertFalse($warnings->has('clowns'));
        self::assertFalse($warnings->isEmpty());
        self::assertTrue(ContentWarnings::none()->isEmpty());
        self::assertSame(['spiders', 'gore'], array_map(static fn (ContentWarning $warning): string => $warning->theme, iterator_to_array($warnings)));
        self::assertSame([0, 1], array_keys(iterator_to_array($warnings)));
        self::assertSame(['gore', 'spiders'], ContentWarnings::forThemes('gore', 'spiders')->themes());
        self::assertSame(['a'], ContentWarnings::of(...['x' => new ContentWarning('a')])->themes());
        self::assertSame(['a'], ContentWarnings::forThemes(...['x' => 'a'])->themes());
    }

    public function testAWarningIsAddedReplacedInItsPlaceOrRemoved(): void
    {
        $warnings = ContentWarnings::forThemes('spiders', 'gore');

        self::assertSame(['spiders', 'gore', 'clowns'], $warnings->with(new ContentWarning('clowns'))->themes());
        self::assertSame(Severity::Severe, $warnings->with(new ContentWarning('spiders', Severity::Severe))->get('spiders')?->severity);
        self::assertSame(['spiders', 'gore'], $warnings->with(new ContentWarning('spiders', Severity::Severe))->themes());
        self::assertSame(['gore'], $warnings->without('spiders')->themes());
        self::assertSame(['spiders', 'gore'], $warnings->without('clowns')->themes());
        self::assertSame(['spiders', 'gore', '2024'], $warnings->with(new ContentWarning('2024'))->themes());
        self::assertSame(['gore', '2024'], $warnings->with(new ContentWarning('2024'))->without('spiders')->themes());
    }

    public function testAThemeIsGivenOnce(): void
    {
        $this->expectExceptionMessageIsOrContains('the theme "gore" is given twice');

        ContentWarnings::forThemes('gore', 'gore');
    }

    public function testAPieceOfContentHasAtMostAHundredWarnings(): void
    {
        $themes = array_map(static fn (int $index): string => 't'.$index, range(1, 100));
        self::assertCount(100, ContentWarnings::forThemes(...$themes));

        $this->expectExceptionMessageIsOrContains('a piece of content has at most 100 warnings');
        ContentWarnings::forThemes(...$themes)->with(new ContentWarning('t101'));
    }

    public function testWarningsAreStoredAndReadBack(): void
    {
        $warnings = ContentWarnings::of(new ContentWarning('spiders'), new ContentWarning('gore', Severity::Severe, 'Chapter 12'), new ContentWarning('clowns', note: 'Act II'));
        $stored = $warnings->toArray();

        self::assertSame([['theme' => 'spiders'], ['theme' => 'gore', 'severity' => 'severe', 'note' => 'Chapter 12'], ['theme' => 'clowns', 'note' => 'Act II']], $stored);
        self::assertEquals($warnings, ContentWarnings::fromArray($stored));
        self::assertEquals(ContentWarnings::forThemes('a'), ContentWarnings::fromArray([['theme' => 'a', 'severity' => null, 'note' => null]]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function unreadable(): iterable
    {
        $shape = 'each warning is an array with a "theme" and an optional "severity" and "note"';

        yield 'not an array' => [['gore'], $shape];
        yield 'an unknown key' => [[['theme' => 'gore', 'level' => 'x']], $shape];
        yield 'no theme' => [[['severity' => 'mild']], $shape];
        yield 'a theme that is no string' => [[['theme' => 3]], $shape];
        yield 'a severity that is no string' => [[['theme' => 'gore', 'severity' => 1]], 'the severity and the note of "gore" are strings'];
        yield 'a note that is no string' => [[['theme' => 'gore', 'note' => 1]], 'the severity and the note of "gore" are strings'];
        yield 'an unknown severity' => [[['theme' => 'gore', 'severity' => 'extreme']], '"extreme" is not a severity'];
        yield 'a malformed theme' => [[['theme' => 'Gore']], '"Gore" is not a theme key'];
        yield 'a theme twice' => [[['theme' => 'gore'], ['theme' => 'gore']], 'cannot be read: Invalid content warning: the theme "gore" is given twice.'];
    }

    public function testTheMessagesEndWithOneFullStop(): void
    {
        try {
            ContentWarnings::fromArray([['theme' => 'Gore']]);
            self::fail('The warning is read.');
        } catch (UnreadableWarnings $unreadableWarnings) {
            self::assertSame('The stored content warnings cannot be read: Invalid content warning: "Gore" is not a theme key.', $unreadableWarnings->getMessage());
        }

        self::assertSame('Invalid content warning: x..', InvalidWarning::because('x.')->getMessage());
    }

    /**
     * @param array<array-key, mixed> $stored
     */
    #[DataProvider('unreadable')]
    public function testWhatToArrayDoesNotWriteIsUnreadable(array $stored, string $message): void
    {
        $this->expectException(UnreadableWarnings::class);
        $this->expectExceptionMessageIsOrContains($message);

        ContentWarnings::fromArray($stored);
    }
}
