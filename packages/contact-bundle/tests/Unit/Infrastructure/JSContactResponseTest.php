<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use Phil\Contact\ContactCard;
use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactWriter;
use Phil\ContactBundle\Infrastructure\Http\JSContactResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JSContactResponse::class)]
final class JSContactResponseTest extends TestCase
{
    public function testACardIsServedAsJSContact(): void
    {
        $card = new JSContactCard('urn:uuid:1', ContactCard::of('Ada'));
        $response = new JSContactResponse($card);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(JSContactWriter::toJson($card), $response->getContent());
        self::assertSame('application/jscontact+json', $response->headers->get('Content-Type'));
        self::assertFalse($response->headers->has('Content-Disposition'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filenames(): iterable
    {
        yield 'ascii' => ['ada.json', 'attachment; filename=ada.json'];
        yield 'accents' => ['Zoé.json', "attachment; filename=Zo_.json; filename*=utf-8''Zo%C3%A9.json"];
        yield 'only punctuation left' => ['é.é', "attachment; filename=contact.json; filename*=utf-8''%C3%A9.%C3%A9"];
        yield 'a slash and a backslash' => ['A/B\\C.json', 'attachment; filename=A_B_C.json'];
        yield 'a percent' => ['100%.json', "attachment; filename=100_.json; filename*=utf-8''100%25.json"];
    }

    #[DataProvider('filenames')]
    public function testWithAFileNameTheCardIsDownloaded(string $filename, string $disposition): void
    {
        self::assertSame($disposition, new JSContactResponse(new JSContactCard('u', ContactCard::of('Ada')), $filename)->headers->get('Content-Disposition'));
    }
}
