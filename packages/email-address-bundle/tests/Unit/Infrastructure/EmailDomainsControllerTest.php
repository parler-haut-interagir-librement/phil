<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EmailDomainsController::class)]
final class EmailDomainsControllerTest extends TestCase
{
    public function testTheListsAreServedPublicForADay(): void
    {
        $response = new EmailDomainsController(new EmailAddresses('key', ['info']), ['gmail.com'], ['con' => 'com'])(Request::create('/domains.json'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"domains":["gmail.com"],"slips":{"con":"com"},"roles":["info"]}', $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('86400', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('"'.hash('xxh128', $response->getContent()).'"', $response->getEtag());
    }

    public function testNoSlipIsAnEmptyObjectAndTheAgeIsGiven(): void
    {
        $response = new EmailDomainsController(new EmailAddresses('key', []), [], [], 60)(Request::create('/domains.json'));

        self::assertSame('{"domains":[],"slips":{},"roles":[]}', $response->getContent());
        self::assertSame('60', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testARequestThatSendsBackTheEtagIsNotModified(): void
    {
        $controller = new EmailDomainsController(new EmailAddresses('key'), ['gmail.com'], []);
        $etag = (string) $controller(Request::create('/domains.json'))->getEtag();
        $request = Request::create('/domains.json');
        $request->headers->set('If-None-Match', $etag);

        $response = $controller($request);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }
}
