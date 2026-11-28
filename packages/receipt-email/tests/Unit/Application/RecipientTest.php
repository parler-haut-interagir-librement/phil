<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Application;

use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

#[CoversClass(Recipient::class)]
final class RecipientTest extends TestCase
{
    public function testARecipientHasAnAddressAndALocale(): void
    {
        $recipient = new Recipient(new Address('ada@example.com', '  Ada  '), 'fr_CA');

        self::assertSame('ada@example.com', $recipient->address->getAddress());
        self::assertSame('fr_CA', $recipient->locale);
        self::assertSame('Ada', $recipient->name());
        self::assertNull(new Recipient(new Address('ada@example.com', ' '), 'en')->name());
        self::assertSame('ast_Latn123', new Recipient(new Address('ada@example.com'), 'ast_Latn123')->locale);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase language' => ['FR'];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fran'];
        yield 'dash' => ['fr-CA'];
        yield 'short region' => ['fr_C'];
        yield 'long region' => ['fr_ABCDEFGHI'];
        yield 'line break' => ["fr\n"];
        yield 'prefix' => ['xfr_CA!'];
    }

    #[DataProvider('refusedLocales')]
    public function testALocaleIsALanguageAndARegion(string $locale): void
    {
        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs('A locale is a language code, then optionally "_" and a region, as fr_CA.');

        new Recipient(new Address('ada@example.com'), $locale);
    }
}
