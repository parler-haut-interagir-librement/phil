<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Translation;

use Phil\SeoCheck\Message;
use Phil\SeoCheck\Translation\English;
use Phil\SeoCheck\Translation\French;
use Phil\SeoCheck\Translation\Messages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[CoversClass(Messages::class)]
#[CoversClass(English::class)]
#[CoversClass(French::class)]
final class MessagesTest extends TestCase
{
    public function testEveryLanguageHasEveryMessageWithTheSamePlaceholders(): void
    {
        self::assertSame(['en', 'fr'], array_keys(Messages::resources()));
        self::assertSame(array_keys(English::MESSAGES), array_keys(French::MESSAGES));

        foreach (English::MESSAGES as $key => $message) {
            self::assertSame($this->placeholders($message), $this->placeholders(French::MESSAGES[$key]), $key);
        }
    }

    public function testEveryKeyTheCodeWritesHasAMessage(): void
    {
        $keys = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 3).'/src'));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && 'php' === $file->getExtension() && !str_contains($file->getPathname(), '/Translation/')) {
                preg_match_all("/new Message\\('([a-z0-9-]+\\.[a-z0-9-]+)'/", (string) file_get_contents($file->getPathname()), $matches);
                array_push($keys, ...$matches[1]);
            }
        }

        $keys = array_unique([...$keys, 'severity.error', 'severity.warning', 'severity.info']);
        sort($keys);
        $known = array_keys(English::MESSAGES);
        sort($known);

        self::assertSame($known, $keys);
    }

    public function testAMessageIsWrittenInTheLocaleOrInEnglish(): void
    {
        $translator = Messages::translator();
        $message = new Message('single-h1.multiple', ['count' => 3]);

        self::assertSame('The page has 3 first-level headings: keep one, and make the others h2.', Messages::translate($translator, $message, 'en'));
        self::assertSame('La page a 3 titres de premier niveau : gardez-en un et faites des autres des h2.', Messages::translate($translator, $message, 'fr-ca'));
        self::assertSame('The page has 3 first-level headings: keep one, and make the others h2.', Messages::translate($translator, $message, 'de'));
        self::assertSame('The page has 3 first-level headings: keep one, and make the others h2.', Messages::translate($translator, $message, 'not a tag'));
        self::assertSame('unknown.key', Messages::translate($translator, new Message('unknown.key'), 'fr'));
        self::assertSame('en', $translator->getLocale());
    }

    #[DataProvider('locales')]
    public function testALanguageTagIsReadAsALocale(string $tag, string $locale): void
    {
        self::assertSame($locale, Messages::locale($tag));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'a language' => ['FR', 'fr'];
        yield 'a region' => ['fr-ca', 'fr_CA'];
        yield 'a script and a region' => ['ZH-hant_tw', 'zh_Hant_TW'];
        yield 'a variant' => ['de-CH-1996', 'de_CH_1996'];
        yield 'a three-letter subtag' => ['es-LATAM', 'es_latam'];
        yield 'a numeric region' => ['es-419', 'es_419'];
        yield 'not a tag' => ['fr CA', 'en'];
        yield 'a trailing line' => ["fr\n", 'en'];
        yield 'empty' => ['', 'en'];
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $message): array
    {
        preg_match_all('/\{[a-z]+\}/', $message, $matches);
        $placeholders = array_unique($matches[0]);
        sort($placeholders);

        return $placeholders;
    }

    public function testAScriptWrittenInCapitalsIsReadInTitleCase(): void
    {
        self::assertSame('zh_Hant', Messages::locale('zh-HANT'));
    }
}
