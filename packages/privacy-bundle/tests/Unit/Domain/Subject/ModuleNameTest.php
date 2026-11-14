<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleName::class)]
final class ModuleNameTest extends TestCase
{
    public function testALowercaseNameIsAccepted(): void
    {
        self::assertSame('identity.profile', ModuleName::fromString('identity.profile')->value);
        self::assertSame(str_repeat('m', 64), ModuleName::fromString(str_repeat('m', 64))->value);
    }

    public function testALongerNameIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        ModuleName::fromString(str_repeat('m', 65));
    }

    public function testATrailingForbiddenCharacterIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        ModuleName::fromString('identity!');
    }

    public function testAnUppercaseNameIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        ModuleName::fromString('Identity');
    }

    public function testATrailingNewlineIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        ModuleName::fromString("identity\n");
    }
}
