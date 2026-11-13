<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CheckinRequest::class)]
final class CheckinRequestTest extends TestCase
{
    public function testTheCookieNameIsTheValueOfTheStatelessToken(): void
    {
        self::assertSame('csrf-token', new CheckinRequest(new FixedTokens())->cookieName());
    }

    public function testARequestOfTheControllerIsTrusted(): void
    {
        $requests = new CheckinRequest(new FixedTokens());

        self::assertTrue($requests->isTrusted($this->request('POST', ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_CSRF_TOKEN' => 'good'])));
        self::assertTrue($requests->isTrusted($this->request('POST', ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_CSRF_TOKEN' => 'good', 'HTTP_SEC_FETCH_SITE' => 'same-origin'])));
        self::assertTrue($requests->isTrusted($this->request('GET', ['HTTP_X_PHIL_CHECKIN' => '1'])));
    }

    public function testARequestWithoutTheHeaderFromAnotherSiteOrWithoutTheTokenIsNot(): void
    {
        $requests = new CheckinRequest(new FixedTokens());

        self::assertFalse($requests->isTrusted($this->request('GET', [])));
        self::assertFalse($requests->isTrusted($this->request('POST', ['HTTP_X_PHIL_CHECKIN' => 'yes', 'HTTP_CSRF_TOKEN' => 'good'])));
        self::assertFalse($requests->isTrusted($this->request('GET', ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_SEC_FETCH_SITE' => 'cross-site'])));
        self::assertFalse($requests->isTrusted($this->request('POST', ['HTTP_X_PHIL_CHECKIN' => '1'])));
        self::assertFalse($requests->isTrusted($this->request('POST', ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_CSRF_TOKEN' => 'bad'])));
    }

    /**
     * @param array<string, string> $server
     */
    private function request(string $method, array $server): Request
    {
        return Request::create('/_checkin/scan', $method, server: $server);
    }
}
