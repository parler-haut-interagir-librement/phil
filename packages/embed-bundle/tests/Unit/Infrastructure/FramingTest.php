<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Infrastructure;

use Phil\EmbedBundle\Application\Attribute\Embeddable;
use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\EmbedContext;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener;
use Phil\EmbedBundle\Infrastructure\Http\HeaderFraming;
use Phil\EmbedBundle\Tests\Fixtures\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(EmbedRequestListener::class)]
#[CoversClass(HeaderFraming::class)]
final class FramingTest extends TestCase
{
    public function testASubRequestIsLeftAlone(): void
    {
        $request = Request::create('/x?embed_token='.$this->token());
        $request->attributes->set('_embed', 'form');
        $this->listener()(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($request->attributes->has(EmbedRequestListener::ATTRIBUTE));
        self::assertFalse($request->attributes->has(HeaderFraming::ATTRIBUTE));
    }

    public function testARefusedTokenIsLoggedWithoutTheToken(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];
            }
        };
        $request = Request::create('/x?embed_token=nope');
        $request->attributes->set('_embed', 'form');
        $this->listener($logger)(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([['An embed token was refused: {reason}', ['reason' => 'An embed token has five parts.', 'embed' => 'form']]], $logger->records);
        self::assertSame("'none'", $request->attributes->get(HeaderFraming::ATTRIBUTE));
    }

    public function testAnEmbedNameThatIsNotAStringIsIgnored(): void
    {
        $request = Request::create('/x?embed_token='.$this->token());
        $request->attributes->set('_embed', ['form']);
        $this->listener()(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertFalse($request->attributes->has(EmbedRequestListener::ATTRIBUTE));
    }

    public function testARefusedTokenWithoutALoggerIsStillRefused(): void
    {
        $request = Request::create('/x?embed_token=nope');
        $request->attributes->set('_embed', 'form');
        $this->listener()(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame("'none'", $request->attributes->get(HeaderFraming::ATTRIBUTE));
    }

    public function testTheEmbedOfAMarkedControllerIsItsFirstAttribute(): void
    {
        $request = Request::create('/x?embed_token='.$this->token());
        $controller = static fn (): null => null;
        $event = new ControllerEvent($this->kernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($controller, [new Embeddable('form')]);
        $this->listener()($event);

        self::assertInstanceOf(EmbedContext::class, $request->attributes->get(EmbedRequestListener::ATTRIBUTE));
        self::assertSame('https://partner.example', $request->attributes->get(HeaderFraming::ATTRIBUTE));
    }

    public function testNoEmbedNoTokenAnEmptyTokenOrAnArrayLeavesTheRequestAlone(): void
    {
        foreach ([
            ['/x?embed_token='.$this->token(), null],
            ['/x', 'form'],
            ['/x?embed_token=', 'form'],
            ['/x?embed_token[]=a', 'form'],
        ] as [$uri, $embed]) {
            $request = Request::create($uri);

            if (null !== $embed) {
                $request->attributes->set('_embed', $embed);
            }

            $this->listener()(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

            self::assertFalse($request->attributes->has(EmbedRequestListener::ATTRIBUTE), $uri);
            self::assertFalse($request->attributes->has(HeaderFraming::ATTRIBUTE), $uri);
        }
    }

    public function testThePageAsksForATheme(): void
    {
        $clock = new MockClock('@1000');
        $tokens = new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([Embed::of('cal', themes: ['light', 'dark'], defaultTheme: 'light')]), $clock);
        $token = $tokens->issue('cal', Origin::fromString('https://partner.example'));

        foreach ([['&embed_theme=dark', 'dark'], ['', 'light'], ['&embed_theme[]=dark', 'light']] as [$query, $theme]) {
            $request = Request::create('/x?embed_token='.$token.$query);
            $request->attributes->set('_embed', 'cal');
            new EmbedRequestListener($tokens, new HeaderFraming())(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

            $context = $request->attributes->get(EmbedRequestListener::ATTRIBUTE);
            self::assertInstanceOf(EmbedContext::class, $context);
            self::assertSame($theme, $context->theme, $query);
        }
    }

    public function testLocalhostIsAnOriginOnlyWhenAllowed(): void
    {
        $clock = new MockClock('@1000');
        $tokens = new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock, true), new Embeds([Embed::of('form')]), $clock);
        $token = $tokens->issue('form', Origin::fromString('http://localhost:9188', true));

        foreach ([[false, "'none'"], [true, 'http://localhost:9188']] as [$allowed, $framing]) {
            $request = Request::create('/x?embed_token='.$token);
            $request->attributes->set('_embed', 'form');
            $listener = $allowed ? new EmbedRequestListener($tokens, new HeaderFraming(), null, true) : new EmbedRequestListener($tokens, new HeaderFraming());
            $listener(new ControllerEvent($this->kernel(), static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST));

            self::assertSame($framing, $request->attributes->get(HeaderFraming::ATTRIBUTE));
        }
    }

    public function testTheHeaderOnlyOnTheMainResponseOfADecidedRequest(): void
    {
        $framing = new HeaderFraming();
        $request = Request::create('/');
        $framing->allow($request, Origin::fromString('https://partner.example'));

        $sub = new Response('', Response::HTTP_OK, ['X-Frame-Options' => 'DENY']);
        $framing->onResponse(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::SUB_REQUEST, $sub));
        self::assertFalse($sub->headers->has('Content-Security-Policy'));
        self::assertSame('DENY', $sub->headers->get('X-Frame-Options'));

        $main = new Response('', Response::HTTP_OK, ['X-Frame-Options' => 'DENY', 'Content-Security-Policy' => "default-src 'self'"]);
        $framing->onResponse(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $main));
        self::assertSame(["default-src 'self'", 'frame-ancestors https://partner.example'], $main->headers->all('content-security-policy'));
        self::assertFalse($main->headers->has('X-Frame-Options'));

        $undecided = new Response('', Response::HTTP_OK, ['X-Frame-Options' => 'DENY']);
        $framing->onResponse(new ResponseEvent($this->kernel(), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $undecided));
        self::assertFalse($undecided->headers->has('Content-Security-Policy'));

        $denied = Request::create('/');
        $framing->deny($denied);
        $refused = new Response('', Response::HTTP_OK, ['X-Frame-Options' => 'DENY']);
        $framing->onResponse(new ResponseEvent($this->kernel(), $denied, HttpKernelInterface::MAIN_REQUEST, $refused));
        self::assertSame(["frame-ancestors 'none'"], $refused->headers->all('content-security-policy'));
        self::assertSame('DENY', $refused->headers->get('X-Frame-Options'));
    }

    private function listener(?AbstractLogger $logger = null): EmbedRequestListener
    {
        $clock = new MockClock('@1000');

        return new EmbedRequestListener(new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([Embed::of('form')]), $clock), new HeaderFraming(), $logger);
    }

    private function token(): string
    {
        $clock = new MockClock('@1000');

        return new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([Embed::of('form')]), $clock)->issue('form', Origin::fromString('https://partner.example'));
    }

    private function kernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
