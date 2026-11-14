<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Phil\SettingsBundle\Application\DocumentAttributes;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Infrastructure\Twig\SettingsExtension;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedVisitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(SettingsExtension::class)]
final class SettingsExtensionTest extends TestCase
{
    public function testTheRootElementCarriesTheDisplayPreferencesAndASettingIsRead(): void
    {
        $catalog = Catalogs::sample();
        $settings = new Settings($catalog, new InMemorySettingRepository(), new FixedUser('ada'), new FixedVisitor(), new MockClock());
        $twig = new Environment(new ArrayLoader([
            'page' => '<html {{ phil_document_attributes() }}>',
            'setting' => '{{ phil_setting("display.motion") }}|{{ phil_setting("author.columns", "bob") }}',
        ]), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new SettingsExtension($settings, new DocumentAttributes($catalog, $settings)));

        self::assertSame('<html >', $twig->render('page'));

        $settings->save(Subject::user('ada'), ['display.motion' => 'reduce', 'display.text_size' => 'large']);
        $settings->save(Subject::user('bob'), ['author.columns' => 4]);

        self::assertSame('<html data-motion="reduce" data-text-size="large">', $twig->render('page'), 'unescaped');
        self::assertSame('reduce|4', $twig->render('setting'));
    }
}
