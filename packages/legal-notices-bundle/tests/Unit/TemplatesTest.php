<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use Phil\LegalNoticesBundle\PhilLegalNoticesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every word of the pages comes from the translations: a template writes no text of its own, and each key it names is
 * translated in English and in French.
 */
#[CoversClass(PhilLegalNoticesBundle::class)]
final class TemplatesTest extends TestCase
{
    private const string TEMPLATES = __DIR__.'/../../templates';

    private const string TRANSLATIONS = __DIR__.'/../../translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        $paths = glob(self::TEMPLATES.'/*.html.twig');
        self::assertNotFalse($paths);
        self::assertNotSame([], $paths);

        foreach ($paths as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('templates')]
    public function testATemplateWritesNoTextOfItsOwn(string $path): void
    {
        $source = (string) file_get_contents($path);
        $text = preg_replace(['/\{#.*?#\}/s', '/\{%.*?%\}/s', '/\{\{.*?\}\}/s', '/<[^>]*>/s'], ' ', $source);

        self::assertIsString($text);
        self::assertSame('', trim($text), basename($path).' writes text outside of Twig.');
    }

    public function testEveryKeyOfTheTemplatesIsTranslatedInEachLanguage(): void
    {
        $keys = [];

        foreach (self::templates() as [$path]) {
            preg_match_all("/'([a-z_]+\\.[a-z_]+)'\\|trans/", (string) file_get_contents($path), $matches);
            $keys = [...$keys, ...$matches[1]];
        }

        // The keys the templates build from a value: the title of each document, the standing of a consent category.
        $keys = [...$keys, 'title.legal_notice', 'title.privacy_policy', 'consent.necessary', 'consent.optional', 'link.legal_notice', 'link.privacy_policy'];
        self::assertContains('consent.category', $keys);

        $english = $this->catalogue('en');

        foreach (['en', 'fr'] as $locale) {
            $catalogue = $this->catalogue($locale);

            foreach (array_unique($keys) as $key) {
                self::assertArrayHasKey($key, $catalogue, \sprintf('"%s" is not translated in %s.', $key, $locale));
                self::assertNotSame('', trim($catalogue[$key]));
            }

            self::assertSame(array_keys($english), array_keys($catalogue), $locale.' has the keys of English, in their order.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function catalogue(string $locale): array
    {
        $catalogue = require self::TRANSLATIONS.'/phil_legal_notices.'.$locale.'.php';
        self::assertIsArray($catalogue);
        $texts = [];

        foreach ($catalogue as $key => $text) {
            self::assertIsString($key);
            self::assertIsString($text);
            $texts[$key] = $text;
        }

        return $texts;
    }
}
