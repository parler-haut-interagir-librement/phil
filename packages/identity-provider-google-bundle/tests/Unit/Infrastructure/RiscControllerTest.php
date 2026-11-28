<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderGoogleBundle\Infrastructure\Http\RiscController;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\RiscHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(RiscController::class)]
final class RiscControllerTest extends TestCase
{
    public function testATokenIsAcceptedAndActedOn(): void
    {
        $harness = new RiscHarness();
        $response = new RiscController($harness->receiver)($this->post(RiscHarness::token('risc/event-type/sessions-revoked')."\n", 'application/secevent+jwt; charset=utf-8'));

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame([], $harness->openSessions());
    }

    public function testTheOlderMediaTypeIsAccepted(): void
    {
        $harness = new RiscHarness();

        self::assertSame(202, new RiscController($harness->receiver)($this->post(RiscHarness::token('risc/event-type/verification'), ' Application/JWT'))->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function refused(): iterable
    {
        yield 'another media type' => ['x.y.z', 'application/json', 'invalid_request', 'The body is not application/secevent+jwt.'];
        yield 'no media type' => ['x.y.z', '', 'invalid_request', 'The body is not application/secevent+jwt.'];
        yield 'not a token' => ['nope', 'application/secevent+jwt', 'invalid_request', 'The body is not a security event token in the compact form.'];
        yield 'another audience' => [RiscHarness::token('risc/event-type/verification', claims: ['aud' => 'other']), 'application/secevent+jwt', 'invalid_audience', 'The token is not meant for this receiver.'];
    }

    #[DataProvider('refused')]
    public function testARefusedTokenIsAnsweredWithItsError(string $body, string $type, string $error, string $description): void
    {
        $response = new RiscController(new RiscHarness()->receiver)($this->post($body, $type));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['err' => $error, 'description' => $description], json_decode((string) $response->getContent(), true));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testABodyTooLongIsRefusedBeforeItIsRead(): void
    {
        $request = $this->post('x.y.z', 'application/secevent+jwt');
        $request->headers->set('Content-Length', '16385');

        $response = new RiscController(new RiscHarness()->receiver)($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['err' => 'invalid_request', 'description' => 'The body is too long.'], json_decode((string) $response->getContent(), true));
    }

    public function testABodyOfTheLongestLengthIsRead(): void
    {
        $request = $this->post('nope', 'application/secevent+jwt');
        $request->headers->set('Content-Length', '16384');

        $response = new RiscController(new RiscHarness()->receiver)($request);

        self::assertSame(['err' => 'invalid_request', 'description' => 'The body is not a security event token in the compact form.'], json_decode((string) $response->getContent(), true));
    }

    public function testALengthThatIsNotANumberIsNotTooLong(): void
    {
        $request = $this->post('nope', 'application/secevent+jwt');
        $request->headers->set('Content-Length', 'abc');

        $response = new RiscController(new RiscHarness()->receiver)($request);

        self::assertSame(['err' => 'invalid_request', 'description' => 'The body is not a security event token in the compact form.'], json_decode((string) $response->getContent(), true));
    }

    public function testKeysThatCannotBeReadAskForARetry(): void
    {
        $harness = new RiscHarness();
        $harness->google->answers[FakeGoogle::RISC_CONFIGURATION] = new MockResponse('down', ['http_code' => 503]);

        $response = new RiscController($harness->receiver)($this->post(RiscHarness::token('risc/event-type/sessions-revoked'), 'application/secevent+jwt'));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertCount(2, $harness->openSessions());
    }

    public function testTheEndpointIsNotFoundWhileTheReceiverIsOff(): void
    {
        $this->expectException(NotFoundHttpException::class);

        new RiscController()($this->post('x.y.z', 'application/secevent+jwt'));
    }

    private function post(string $body, string $type): Request
    {
        $request = Request::create('https://example.com/identity/google/risc', 'POST', content: $body);

        if ('' !== $type) {
            $request->headers->set('Content-Type', $type);
        } else {
            $request->headers->remove('Content-Type');
        }

        return $request;
    }
}
