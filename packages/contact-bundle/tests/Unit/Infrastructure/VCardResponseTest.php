<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use Phil\Contact\ContactCard;
use Phil\Contact\VCard;
use Phil\ContactBundle\Infrastructure\Http\VCardResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VCardResponse::class)]
final class VCardResponseTest extends TestCase
{
    public function testACardIsDownloadedAsAFile(): void
    {
        $card = ContactCard::of('Ada');
        $response = new VCardResponse($card);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(VCard::of($card), $response->getContent());
        self::assertSame('text/vcard; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=contact.vcf', $response->headers->get('Content-Disposition'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filenames(): iterable
    {
        yield 'ascii' => ['shop.vcf', 'attachment; filename=shop.vcf'];
        yield 'accents' => ['Zoé.vcf', "attachment; filename=Zo_.vcf; filename*=utf-8''Zo%C3%A9.vcf"];
        yield 'one readable letter' => ['été', "attachment; filename=_t_; filename*=utf-8''%C3%A9t%C3%A9"];
        yield 'only punctuation left' => ['é.é', "attachment; filename=contact.vcf; filename*=utf-8''%C3%A9.%C3%A9"];
        yield 'a slash' => ['A/B Café.vcf', "attachment; filename=\"A_B Caf_.vcf\"; filename*=utf-8''A_B%20Caf%C3%A9.vcf"];
        yield 'a backslash' => ['A\\B.vcf', 'attachment; filename=A_B.vcf'];
        yield 'a percent' => ['100%.vcf', "attachment; filename=100_.vcf; filename*=utf-8''100%25.vcf"];
    }

    #[DataProvider('filenames')]
    public function testAFileNameHasAnAsciiFallback(string $filename, string $disposition): void
    {
        self::assertSame($disposition, new VCardResponse(ContactCard::of('Ada'), $filename)->headers->get('Content-Disposition'));
    }
}
