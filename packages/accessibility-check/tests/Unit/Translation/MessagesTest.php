<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Translation;

use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Translation\English;
use Phil\AccessibilityCheck\Translation\French;
use Phil\AccessibilityCheck\Translation\German;
use Phil\AccessibilityCheck\Translation\Italian;
use Phil\AccessibilityCheck\Translation\Messages;
use Phil\AccessibilityCheck\Translation\Portuguese;
use Phil\AccessibilityCheck\Translation\Spanish;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Messages::class)]
#[CoversClass(Message::class)]
final class MessagesTest extends TestCase
{
    public function testTheMessagesSpeakSixLanguages(): void
    {
        self::assertSame(['en', 'fr', 'es', 'de', 'it', 'pt'], array_keys(Messages::resources()));
        self::assertSame(English::MESSAGES, Messages::resources()['en']);
        self::assertSame(French::MESSAGES, Messages::translator()->getCatalogue('fr')->all(Messages::DOMAIN));
        self::assertSame('en', Messages::translator()->getLocale());
    }

    public function testALanguageTagIsReadAsALocale(): void
    {
        self::assertSame('fr_CA', Messages::locale('FR-ca'));
        self::assertSame('zh_Hant_TW', Messages::locale('ZH-HANT-tw'));
        self::assertSame('sl_IT_rozaj', Messages::locale('sl-it-ROZAJ'));
        self::assertSame('es_419', Messages::locale('es_419'));
        self::assertSame('de', Messages::locale('DE'));
        self::assertSame('en', Messages::locale('fr CA'));
        self::assertSame('en', Messages::locale('../fr'));
        self::assertSame('en', Messages::locale(''));
        self::assertSame('en', Messages::locale('f'));
        self::assertSame('en', Messages::locale('fr_'));
        self::assertSame('en', Messages::locale("fr\n"));
    }

    /**
     * @return iterable<array{array<string, string>, string}>
     */
    public static function translations(): iterable
    {
        yield 'fr' => [French::MESSAGES, 'fr'];
        yield 'es' => [Spanish::MESSAGES, 'es'];
        yield 'de' => [German::MESSAGES, 'de'];
        yield 'it' => [Italian::MESSAGES, 'it'];
        yield 'pt' => [Portuguese::MESSAGES, 'pt'];
    }

    /**
     * @param array<string, string> $messages
     */
    #[DataProvider('translations')]
    #[CoversNothing]
    public function testEveryLanguageHasEveryMessageWithItsPlaceholders(array $messages, string $locale): void
    {
        self::assertSame(array_keys(English::MESSAGES), array_keys($messages), $locale);

        foreach (English::MESSAGES as $key => $english) {
            preg_match_all('/\{\w+\}/', $english, $expected);
            preg_match_all('/\{\w+\}/', $messages[$key], $found);
            sort($expected[0]);
            sort($found[0]);

            self::assertSame($expected[0], $found[0], $locale.' '.$key);
        }
    }

    public function testAMessageIsWrittenInTheLocaleWithItsValues(): void
    {
        $message = new Message('empty-heading.empty', ['level' => 3]);
        $translator = Messages::translator();

        self::assertSame('This h3 heading has no text.', Messages::translate($translator, $message, 'en'));
        self::assertSame('Ce titre h3 n’a pas de texte.', Messages::translate($translator, $message, 'fr'));
        self::assertSame('Ce titre h3 n’a pas de texte.', Messages::translate($translator, $message, 'FR_ca'));
        self::assertSame('Este encabezado h3 no tiene texto.', Messages::translate($translator, $message, 'es-419'));
        self::assertSame('This h3 heading has no text.', Messages::translate($translator, $message, 'ja'));
        self::assertSame('unknown.key', Messages::translate($translator, new Message('unknown.key', ['x' => 1]), 'fr'));
    }

    public function testAnApplicationAddsOrReplacesMessages(): void
    {
        $translator = Messages::translator();
        $translator->addResource('array', ['empty-heading.empty' => 'Le titre h{level} est vide.', 'my-rule.found' => 'Trouvé {what}.'], 'fr_CA', Messages::DOMAIN);
        $translator->addResource('array', ['empty-heading.empty' => 'Deze kop h{level} is leeg.'], 'nl', Messages::DOMAIN);

        self::assertSame('Le titre h2 est vide.', Messages::translate($translator, new Message('empty-heading.empty', ['level' => 2]), 'fr-ca'));
        self::assertSame('Trouvé 1,5.', Messages::translate($translator, new Message('my-rule.found', ['what' => '1,5']), 'fr-CA'));
        self::assertSame('Ce titre h2 n’a pas de texte.', Messages::translate($translator, new Message('empty-heading.empty', ['level' => 2]), 'fr'));
        self::assertSame('Deze kop h2 is leeg.', Messages::translate($translator, new Message('empty-heading.empty', ['level' => 2]), 'nl'));
    }
}
