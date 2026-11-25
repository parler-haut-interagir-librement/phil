<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Application\CelebrationMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CelebrationMessages::class)]
final class CelebrationMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function locales(): iterable
    {
        yield 'French' => ['fr', 'fr'];
        yield 'French of Canada, ICU' => ['fr_CA', 'fr'];
        yield 'French of Canada, BCP 47' => ['fr-CA', 'fr'];
        yield 'upper case' => ['FR', 'fr'];
        yield 'English' => ['en_GB', 'en'];
        yield 'another language' => ['de', 'en'];
        yield 'a language that starts like French' => ['fra', 'en'];
        yield 'none' => [null, 'en'];
        yield 'empty' => ['', 'en'];
    }

    #[DataProvider('locales')]
    public function testTheWordsOfTheLanguageOfALocaleEnglishForAnyOther(?string $locale, string $language): void
    {
        self::assertSame(CelebrationMessages::MESSAGES[$language], new CelebrationMessages()->of($locale));
    }

    public function testEachLanguageHasTheSameNumberOfWordsEachSaidOnce(): void
    {
        foreach (CelebrationMessages::MESSAGES as $messages) {
            self::assertCount(\count(CelebrationMessages::MESSAGES['en']), $messages);
            self::assertSame($messages, array_values(array_unique($messages)));
            self::assertNotContains('', $messages);
        }
    }
}
