<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use Phil\SettingsBundle\Application\DocumentAttributes;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedVisitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DocumentAttributes::class)]
final class DocumentAttributesTest extends TestCase
{
    public function testTheChoicesOtherThanTheDefaultAreWritten(): void
    {
        $catalog = Catalogs::sample();
        $settings = new Settings($catalog, new InMemorySettingRepository(), new FixedUser('ada'), new FixedVisitor(['display.motion' => 'reduce']), new MockClock());
        $attributes = new DocumentAttributes($catalog, $settings);

        self::assertSame([], $attributes->attributes());

        $settings->save(Subject::user('ada'), ['display.text_size' => 'larger', 'display.contrast' => 'more', 'display.theme' => 'system']);
        self::assertSame(['data-contrast' => 'more', 'data-text-size' => 'larger'], $attributes->attributes());
    }

    public function testAVisitorHasTheirCookie(): void
    {
        $catalog = Catalogs::sample();
        $settings = new Settings($catalog, new InMemorySettingRepository(), new FixedUser(), new FixedVisitor(['display.motion' => 'reduce', 'display.theme' => 'dark']), new MockClock());

        self::assertSame(['data-theme' => 'dark', 'data-motion' => 'reduce'], new DocumentAttributes($catalog, $settings)->attributes());
    }
}
