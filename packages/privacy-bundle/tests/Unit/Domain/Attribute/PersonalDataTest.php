<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Attribute;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonalData::class)]
final class PersonalDataTest extends TestCase
{
    public function testItCarriesACategoryAndARetentionRule(): void
    {
        $declaration = new PersonalData('email', retention: 'R-002');

        self::assertSame('email', $declaration->category->value);
        self::assertSame('R-002', $declaration->retention);
    }

    public function testTheRetentionRuleIsOptional(): void
    {
        self::assertNull(new PersonalData('ip')->retention);
    }

    public function testAFreeTextCategoryIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new PersonalData('e-mail address');
    }

    public function testAMalformedRetentionIdentifierIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new PersonalData('email', retention: 'see the schedule');
    }
}
