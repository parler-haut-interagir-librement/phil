<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Infrastructure;

use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredFonts::class)]
final class ConfiguredFontsTest extends TestCase
{
    public function testAFontIsReadFromItsFile(): void
    {
        $fonts = new ConfiguredFonts(['Sans' => LootShirts::font()]);

        self::assertSame(realpath(LootShirts::font()), $fonts->typeFace('Sans')->path);
    }

    public function testAnUnknownNameIsRefused(): void
    {
        $this->expectExceptionObject(TemplateRefused::unknownFont('Serif'));

        new ConfiguredFonts(['Sans' => LootShirts::font()])->typeFace('Serif');
    }

    public function testAFileThatIsNotAFontIsRefused(): void
    {
        $this->expectException(TemplateRefused::class);
        $this->expectExceptionMessageIsOrContains('The print font Sans cannot be read back: ');

        new ConfiguredFonts(['Sans' => __FILE__])->typeFace('Sans');
    }
}
