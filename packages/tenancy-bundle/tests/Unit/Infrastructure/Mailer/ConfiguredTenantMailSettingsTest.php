<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Mailer;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Mailer\ConfiguredTenantMailSettings;
use Phil\TenancyBundle\Infrastructure\Mailer\TenantMail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredTenantMailSettings::class)]
#[CoversClass(TenantMail::class)]
final class ConfiguredTenantMailSettingsTest extends TestCase
{
    public function testATenantWithItsOwnDomainSendsFromItsSender(): void
    {
        $mail = $this->mail('rose');

        self::assertSame('rose_smtp', $mail->transport);
        self::assertSame('"Rose" <shop@rose.example>', $mail->from?->toString());
        self::assertNull($mail->replyTo);
    }

    public function testTheNameOfATenantNamesItsOwnSender(): void
    {
        $mail = $this->mail('tavern');

        self::assertSame('"The Tavern" <hello@tavern.example>', $mail->from?->toString());
    }

    public function testATenantWithoutADomainSendsFromThePlatformUnderItsNameWithItsReplyAddress(): void
    {
        $mail = $this->mail('fair');

        self::assertNull($mail->transport);
        self::assertSame('"Summer Fair" <mail@platform.example>', $mail->from?->toString());
        self::assertSame('fair.organisers@mail.example', $mail->replyTo?->toString());
    }

    public function testATenantWithoutADomainOrANameSendsUnderThePlatformsName(): void
    {
        $mail = $this->mail('club');

        self::assertSame('"Platform" <mail@platform.example>', $mail->from?->toString());
    }

    public function testATenantWithATransportOnlyKeepsTheApplicationsSender(): void
    {
        $mail = $this->mail('bulk');

        self::assertSame('bulk_smtp', $mail->transport);
        self::assertNull($mail->from);
        self::assertNull($mail->replyTo);
    }

    public function testATenantWithoutSettingsSendsAsTheApplication(): void
    {
        self::assertNull($this->settings()->for(TenantId::fromString('elsewhere')));
    }

    public function testATenantWithoutASenderNeedsThePlatformsSender(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The tenant "fair" has no sender of its own: its mail is sent from phil_tenancy.mailer.platform_sender, which is not set.');

        new ConfiguredTenantMailSettings(null, ['fair' => ['reply_to' => 'fair@mail.example']]);
    }

    public function testANameAloneNeedsThePlatformsSender(): void
    {
        $this->expectException(InvalidValue::class);

        new ConfiguredTenantMailSettings(null, ['fair' => ['name' => 'Fair']]);
    }

    public function testAMalformedAddressIsRefused(): void
    {
        foreach ([['not an address', []], [null, ['rose' => ['sender' => 'rose@']]], ['mail@platform.example', ['fair' => ['reply_to' => '@mail.example']]]] as [$platform, $tenants]) {
            try {
                new ConfiguredTenantMailSettings($platform, $tenants);
                self::fail('The address was accepted.');
            } catch (InvalidValue $refused) {
                self::assertStringStartsWith('phil_tenancy.mailer: "', $refused->getMessage());
            }
        }
    }

    public function testARefusalNamesTheTenantAndTheKeyOfTheAddress(): void
    {
        foreach ([
            ['rose', ['sender' => 'rose@'], '(rose.sender)'],
            ['fair', ['reply_to' => '@mail.example'], '(fair.reply_to)'],
            ['fair', ['sender' => 'fair <'], '(fair.sender)'],
        ] as [$tenant, $settings, $key]) {
            try {
                new ConfiguredTenantMailSettings('mail@platform.example', [$tenant => $settings]);
                self::fail('The address was accepted.');
            } catch (InvalidValue $refused) {
                self::assertStringContainsString($key, $refused->getMessage());
            }
        }
    }

    private function mail(string $tenant): TenantMail
    {
        $mail = $this->settings()->for(TenantId::fromString($tenant));
        self::assertInstanceOf(TenantMail::class, $mail);

        return $mail;
    }

    private function settings(): ConfiguredTenantMailSettings
    {
        return new ConfiguredTenantMailSettings('"Platform" <mail@platform.example>', [
            'rose' => ['transport' => 'rose_smtp', 'sender' => '"Rose" <shop@rose.example>'],
            'tavern' => ['sender' => 'hello@tavern.example', 'name' => 'The Tavern'],
            'fair' => ['name' => 'Summer Fair', 'reply_to' => 'fair.organisers@mail.example'],
            'club' => ['reply_to' => 'club@mail.example'],
            'bulk' => ['transport' => 'bulk_smtp'],
        ]);
    }
}
