<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Http;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Infrastructure\Http\CookieInventoryFactory;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookieListener;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(UndeclaredCookies::class)]
#[CoversClass(UndeclaredCookieListener::class)]
#[CoversClass(CookieInventoryFactory::class)]
final class UndeclaredCookiesTest extends TestCase
{
    public function testTheCookiesAResponseSetsAreCheckedAndThoseItClearsAreNot(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('choice', 'x'));
        $response->headers->setCookie(Cookie::create('_visit', 'x'));
        $response->headers->setCookie(Cookie::create('_pk_id.1.ab', 'x'));
        $response->headers->setCookie(Cookie::create('tracker', 'x'));
        $response->headers->clearCookie('old');

        self::assertSame(['tracker'], UndeclaredCookies::in($response, $this->inventory()));
        self::assertSame([], UndeclaredCookies::in(new Response(), $this->inventory()));
    }

    public function testTheListenerWarnsAndLeavesTheResponseAlone(): void
    {
        $handler = new TestHandler();
        $listener = new UndeclaredCookieListener($this->inventory(), new Logger('test', [$handler]));
        $response = new Response('body');
        $response->headers->setCookie(Cookie::create('tracker', 'x'));
        $response->headers->setCookie(Cookie::create('pixel', 'x'));

        $listener->onKernelResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response));
        $listener->onKernelResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, new Response()));

        self::assertCount(1, $handler->getRecords());
        self::assertSame('The response sets cookies the inventory does not declare: {cookies}.', $handler->getRecords()[0]->message);
        self::assertSame(['cookies' => 'tracker, pixel'], $handler->getRecords()[0]->context);
        self::assertSame('body', $response->getContent());
    }

    public function testTheConsentCookieIsAlwaysDeclared(): void
    {
        $inventory = CookieInventoryFactory::create([], 'phil_privacy_consent', 'P6M');

        self::assertCount(1, $inventory->cookies);
        self::assertSame(['phil_privacy_consent', null, 'P6M'], [$inventory->cookies[0]->name, $inventory->cookies[0]->category, $inventory->cookies[0]->duration]);
        self::assertStringStartsWith('Garde vos choix', $inventory->cookies[0]->purpose('fr'));
        self::assertStringStartsWith('Keeps your cookie choices', $inventory->cookies[0]->purpose('en'));
    }

    private function inventory(): CookieInventory
    {
        return CookieInventoryFactory::create(['analytics' => [
            '_visit' => ['purpose' => 'x', 'duration' => 'P1D'],
            '_pk_id.*' => ['purpose' => 'x', 'duration' => 'P1D'],
        ]], 'choice', 'P6M');
    }
}
