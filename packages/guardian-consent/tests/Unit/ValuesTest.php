<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use Phil\GuardianConsent\ConsentStatus;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\NoticeVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Feature::class)]
#[CoversClass(NoticeVersion::class)]
#[CoversClass(Guardian::class)]
#[CoversClass(ConsentStatus::class)]
#[CoversClass(InvalidConsent::class)]
final class ValuesTest extends TestCase
{
    public function testAFeature(): void
    {
        $feature = new Feature('messages');

        self::assertSame('messages', $feature->name);
        self::assertSame('messages', (string) $feature);
        self::assertTrue($feature->equals(new Feature('messages')));
        self::assertFalse($feature->equals(new Feature('public-profile')));
        self::assertSame('0', new Feature('0')->name);
        self::assertSame('classified_ads.v2', new Feature('classified_ads.v2')->name);
        self::assertSame(64, \strlen(new Feature(str_repeat('a', 64))->name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFeatures(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Messages'];
        yield 'leading dash' => ['-messages'];
        yield 'leading dot' => ['.messages'];
        yield 'space' => ['public profile'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["messages\n"];
    }

    #[DataProvider('invalidFeatures')]
    public function testAFeatureNameOutOfTheRuleIsRefused(string $name): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A feature is named by 1 to 64 lowercase letters, digits, ".", "_" or "-", beginning with a letter or a digit.');

        new Feature($name);
    }

    public function testANoticeVersion(): void
    {
        self::assertSame('2026-11+fr_CA.v3', new NoticeVersion('2026-11+fr_CA.v3')->value);
        self::assertSame('v3', (string) new NoticeVersion('v3'));
        self::assertSame(64, \strlen(new NoticeVersion(str_repeat('A', 64))->value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidVersions(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'space' => ['v 3'];
        yield 'slash' => ['v/3'];
        yield 'trailing newline' => ["v3\n"];
    }

    #[DataProvider('invalidVersions')]
    public function testANoticeVersionOutOfTheRuleIsRefused(string $value): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('The version of a text is 1 to 64 letters, digits, ".", "_", "-" or "+".');

        new NoticeVersion($value);
    }

    public function testAGuardian(): void
    {
        $guardian = new Guardian(new GuardianId('g-1'), 'parent@example.org');

        self::assertSame('g-1', $guardian->id->value);
        self::assertSame('parent@example.org', $guardian->email);

        $longest = str_repeat('a', 64).'@'.str_repeat('b', 189);
        self::assertSame(254, \strlen(new Guardian(new GuardianId('g-1'), $longest)->email));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'no at sign' => ['parent.example.org'];
        yield 'two at signs' => ['parent@home@example.org'];
        yield 'empty local part' => ['@example.org'];
        yield 'empty domain' => ['parent@'];
        yield 'space' => ['par ent@example.org'];
        yield 'trailing newline' => ["parent@example.org\n"];
        yield 'control character' => ["parent\x01@example.org"];
        yield 'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 190)];
    }

    #[DataProvider('invalidAddresses')]
    public function testAnAddressThatCannotBeOneIsRefused(string $email): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('The address of a guardian is one "@" between two parts, without whitespace, at most 254 bytes.');

        new Guardian(new GuardianId('g-1'), $email);
    }

    public function testOnlyAGivenOrNeedlessConsentAllows(): void
    {
        self::assertTrue(ConsentStatus::NotRequired->allows());
        self::assertTrue(ConsentStatus::Granted->allows());
        self::assertFalse(ConsentStatus::Pending->allows());
        self::assertFalse(ConsentStatus::Refused->allows());
        self::assertFalse(ConsentStatus::HandoverRequired->allows());
    }
}
