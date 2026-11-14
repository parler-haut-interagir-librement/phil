<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\EmailTracking;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailTrackingChoice::class)]
final class EmailTrackingChoiceTest extends TestCase
{
    public function testEachPurposeIsAnsweredOnItsOwnUnderTheVersionOfItsText(): void
    {
        $opensOnly = $this->choice('2', true, false);

        self::assertTrue($opensOnly->allowsOpens('2'));
        self::assertFalse($opensOnly->allowsClicks('2'));
        self::assertFalse($opensOnly->allowsOpens('3'), 'A new text asks again.');

        $clicksOnly = $this->choice('2', false, true);

        self::assertFalse($clicksOnly->allowsOpens('2'));
        self::assertTrue($clicksOnly->allowsClicks('2'));
        self::assertFalse($clicksOnly->allowsClicks('1'));
    }

    public function testTheExportShowsTheChoiceDatedInUtc(): void
    {
        $choice = new EmailTrackingChoice(DataSubject::fromString('member-1'), '2', true, false, new DateTimeImmutable('2026-11-14 09:30:00', new DateTimeZone('America/Montreal')));

        self::assertSame(['version' => '2', 'opens' => true, 'clicks' => false, 'decided_at' => '2026-11-14T14:30:00+00:00'], $choice->toArray());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badVersions(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['1 0'];
        yield 'leading dot' => ['.1'];
        yield 'too long' => [str_repeat('a', 33)];
    }

    #[DataProvider('badVersions')]
    public function testAVersionMustBeOneOfATextOfTheSite(string $version): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('This is not a version of the text about tracked emails.');

        $this->choice($version, true, true);
    }

    public function testTheLongestVersionIsTaken(): void
    {
        self::assertTrue($this->choice(str_repeat('a', 32), true, true)->allowsOpens(str_repeat('a', 32)));
    }

    private function choice(string $version, bool $opens, bool $clicks): EmailTrackingChoice
    {
        return new EmailTrackingChoice(DataSubject::fromString('member-1'), $version, $opens, $clicks, new DateTimeImmutable('2026-11-14T12:00:00Z'));
    }
}
