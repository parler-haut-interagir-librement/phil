<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\Feedback;
use Phil\Passwords\Strength\FeedbackMessages;
use Phil\Passwords\Strength\Suggestion;
use Phil\Passwords\Strength\Warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedbackMessages::class)]
final class FeedbackMessagesTest extends TestCase
{
    public function testTheLocaleItsLanguageOrEnglish(): void
    {
        self::assertSame('en', FeedbackMessages::forLocale('en')->locale);
        self::assertSame('fr', FeedbackMessages::forLocale('fr')->locale);
        self::assertSame('fr', FeedbackMessages::forLocale('fr_CA')->locale);
        self::assertSame('fr', FeedbackMessages::forLocale('fr-BE')->locale);
        self::assertSame('en', FeedbackMessages::forLocale('de_DE')->locale);
        self::assertSame('en', FeedbackMessages::forLocale('../../etc/passwd')->locale);
    }

    public function testSentences(): void
    {
        $english = FeedbackMessages::forLocale('en');
        $french = FeedbackMessages::forLocale('fr_CA');

        self::assertSame('This is one of the ten most common passwords.', $english->warning(Warning::TopTen));
        self::assertSame('Ajoutez un mot ou deux, de préférence peu courants.', $french->suggestion(Suggestion::AddWord));
        self::assertSame(
            ['Une date se devine souvent facilement.', 'Ajoutez un mot ou deux, de préférence peu courants.', 'Évitez les dates et les années qui vous sont associées.'],
            $french->describe(new Feedback(Warning::Date, [Suggestion::AddWord, Suggestion::AvoidDates])),
        );
        self::assertSame(['Avoid sequences.'], $english->describe(new Feedback(null, [Suggestion::AvoidSequences])));
    }

    public function testEveryMessageInEveryLocale(): void
    {
        foreach (FeedbackMessages::LOCALES as $locale) {
            $messages = FeedbackMessages::forLocale($locale);

            foreach (Warning::cases() as $warning) {
                self::assertNotSame('', $messages->warning($warning));
            }

            foreach (Suggestion::cases() as $suggestion) {
                self::assertNotSame('', $messages->suggestion($suggestion));
            }

            /** @var array<string, string> $catalogue */
            $catalogue = require \dirname(__DIR__, 3).'/translations/phil_passwords.'.$locale.'.php';
            self::assertCount(\count(Warning::cases()) + \count(Suggestion::cases()), $catalogue);
        }
    }
}
