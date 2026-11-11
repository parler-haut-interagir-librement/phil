<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Hyphenation;

use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dictionary::class)]
#[CoversClass(UnsupportedLanguage::class)]
final class DictionaryTest extends TestCase
{
    /**
     * @return iterable<array{string, ?string}>
     */
    public static function tags(): iterable
    {
        yield ['fr', 'fr'];
        yield ['fr_CA', 'fr'];
        yield ['de', 'de-1996'];
        yield ['de_AT', 'de-1996'];
        yield ['de_CH', 'de-ch-1901'];
        yield ['de-1901', 'de-1901'];
        yield ['la-x-classic', 'la-x-classic'];
        yield ['en', 'en-us'];
        yield ['en_US', 'en-us'];
        yield ['en_GB', 'en-gb'];
        yield ['en_UK', 'en-gb'];
        yield ['nb_NO', 'nn'];
        yield ['nn_NO', 'nn'];
        yield ['sr', 'sh-cyrl'];
        yield ['sr_RS', 'sh-cyrl'];
        yield ['sr_Latn_RS', 'sh-latn'];
        yield ['zh_Latn', 'zh-latn-pinyin'];
        yield ['af_ZA', 'af'];
        yield ['cs', 'cs'];
        yield ['cs_CZ', 'cs'];
        yield ['hu_HU', 'hu'];
        yield ['hy_AM', null];
        yield ['id_ID', 'id'];
        yield ['lv', 'lv'];
        yield ['mk_MK', null];
        yield ['ro_RO', 'ro'];
        yield ['ro_MD', 'ro'];
        yield ['zu_ZA', null];
        yield ['th', null];
        yield ['xx', null];
    }

    #[DataProvider('tags')]
    public function testTheTagOfALanguage(string $language, ?string $tag): void
    {
        $dictionary = new Dictionary();

        self::assertSame($tag, $dictionary->tag($language));
        self::assertSame(null !== $tag, $dictionary->supports($language));

        if (1 === preg_match('/^[a-z]{2,3}(_[A-Za-z]+)*$/', $language)) {
            self::assertSame($tag, $dictionary->tag(Locale::parse($language)));
        }
    }

    public function testTheExperimentalLanguagesAreServedOnlyWhenAskedFor(): void
    {
        self::assertSame(['hy', 'mk'], Dictionary::EXPERIMENTAL);

        $default = new Dictionary();

        foreach (['hy', 'mk', 'hy_AM', 'mk_MK'] as $language) {
            self::assertNull($default->tag($language), $language);
            self::assertFalse($default->supports($language), $language);
        }

        try {
            $default->patterns('hy');
            self::fail('hy');
        } catch (UnsupportedLanguage $unsupportedLanguage) {
            self::assertSame('There are no hyphenation patterns for "hy".', $unsupportedLanguage->getMessage());
        }

        $armenian = new Dictionary(null, ['hy', 'fr']);

        self::assertSame('hy', $armenian->tag('hy'));
        self::assertSame('hy', $armenian->tag(Locale::parse('hy_AM')));
        self::assertTrue($armenian->supports('hy_AM'));
        self::assertSame(2, $armenian->patterns('hy')->left);
        self::assertNull($armenian->tag('mk'));
        self::assertNull($armenian->tag('mk_MK'));
        self::assertSame('fr', $armenian->tag('fr'));

        $both = new Dictionary(null, Dictionary::EXPERIMENTAL);

        self::assertSame('hy', $both->tag('hy_AM'));
        self::assertSame('mk', $both->tag('mk_MK'));
        self::assertSame('cs', $both->tag('cs'));
    }

    public function testADirectoryWithoutTheFileDoesNotSupportTheLanguage(): void
    {
        self::assertFalse(new Dictionary('/nonexistent')->supports('de'));
        self::assertSame('de-1996', new Dictionary('/nonexistent')->tag('de'));
    }

    public function testWhatIsNeitherATagNorALocaleIsRefused(): void
    {
        $this->expectException(InvalidLocale::class);

        new Dictionary()->tag('fr-ch-1901');
    }

    public function testEveryShippedLanguageIsRead(): void
    {
        $dictionary = new Dictionary(null, Dictionary::EXPERIMENTAL);

        foreach (Dictionary::LANGUAGES as $tag => [$name, $left, $right, $licence]) {
            $patterns = $dictionary->patterns($tag);

            self::assertNotSame([], $patterns->patterns, $tag);
            self::assertGreaterThan(0, $patterns->longest, $tag);
            self::assertSame($left, $patterns->left, $tag);
            self::assertSame($right, $patterns->right, $tag);
            self::assertNotSame('', $name);
            self::assertNotSame('?', $licence);
            self::assertFileExists(\dirname(__DIR__, 3).'/data/hyphenation/'.$tag.'.pat.txt');
        }

        foreach (Dictionary::ALIASES as $tag) {
            self::assertArrayHasKey($tag, Dictionary::LANGUAGES);
        }

        self::assertSame(['table' => [2]], array_intersect_key($dictionary->patterns('en-us')->exceptions, ['table' => true]));
        self::assertSame([], $dictionary->patterns('fr')->exceptions);
    }

    public function testPatternsAreReadOnce(): void
    {
        $dictionary = new Dictionary();

        self::assertSame($dictionary->patterns('fr'), $dictionary->patterns(Locale::parse('fr_CA')));
    }

    public function testADirectoryOfItsOwn(): void
    {
        $directory = sys_get_temp_dir().'/phil-typography-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/fr.pat.txt', "a1b\n");
        file_put_contents($directory.'/fr.hyp.txt', 'ta-ble');

        try {
            $dictionary = new Dictionary($directory);
            $patterns = $dictionary->patterns('fr');

            self::assertSame(['ab' => [1 => 1]], $patterns->patterns);
            self::assertSame(['table' => [2]], $patterns->exceptions);
            self::assertSame(['ab' => [1 => 1]], new Dictionary($directory.'/')->patterns('fr')->patterns);

            try {
                $dictionary->patterns('de');
                self::fail('de');
            } catch (UnsupportedLanguage $unsupportedLanguage) {
                self::assertSame('There are no hyphenation patterns for "de-1996".', $unsupportedLanguage->getMessage());
            }
        } finally {
            unlink($directory.'/fr.pat.txt');
            unlink($directory.'/fr.hyp.txt');
            rmdir($directory);
        }
    }

    public function testALanguageWithoutPatterns(): void
    {
        foreach (['th', Locale::parse('zu_ZA')] as $language) {
            try {
                new Dictionary()->patterns($language);
                self::fail('no patterns');
            } catch (UnsupportedLanguage $unsupportedLanguage) {
                self::assertInstanceOf(TypographyException::class, $unsupportedLanguage);
                self::assertSame(\sprintf('There are no hyphenation patterns for "%s".', \is_string($language) ? $language : 'zu_ZA'), $unsupportedLanguage->getMessage());
            }
        }
    }
}
