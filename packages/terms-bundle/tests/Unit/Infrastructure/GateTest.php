<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceGate;
use Phil\TermsBundle\Tests\Unit\Fixture\FixedMember;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(AcceptanceGate::class)]
final class GateTest extends TestCase
{
    public function testAMemberWithAVersionToAcceptIsSentToThePage(): void
    {
        $kit = $this->kit();

        $response = $this->handle($kit, Request::create('/forum/topic?page=2'));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/accept?target=/forum/topic?page%3D2', $response->headers->get('Location'));
    }

    public function testAMemberInOrderAVisitorAndASubRequestPass(): void
    {
        $kit = $this->kit();
        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('42', 'privacy', 1, AcceptanceChannel::Form);

        self::assertNull($this->handle($kit, Request::create('/forum')));
        self::assertNull($this->handle($kit, Request::create('/forum'), new FixedMember()));
        self::assertNull($this->handle($kit, Request::create('/forum'), new FixedMember('43'), HttpKernelInterface::SUB_REQUEST));
        self::assertNotNull($this->handle($kit, Request::create('/forum'), new FixedMember('43')));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function exempt(): iterable
    {
        yield 'a route of the bundle' => ['/accept', 'phil_terms_accept'];
        yield 'an exempt route' => ['/logout', 'logout'];
        yield 'an exempt path' => ['/_profiler/abc', null];
        yield 'another exempt path' => ['/media/x.png', 'media'];
    }

    #[DataProvider('exempt')]
    public function testTheExemptRequestsPass(string $path, ?string $route): void
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        self::assertNull($this->handle($this->kit(), $request));
    }

    public function testARouteThatIsNotExemptIsStopped(): void
    {
        $request = Request::create('/terms-of-sale');
        $request->attributes->set('_route', 'shop_terms');

        self::assertNotNull($this->handle($this->kit(), $request));

        $request = Request::create('/my/_profiler');
        self::assertNotNull($this->handle($this->kit(), $request));
    }

    /**
     * @return iterable<string, array{Request}>
     */
    public static function apis(): iterable
    {
        yield 'an API path' => [Request::create('/api/posts')];
        yield 'a JSON format' => [self::formatted('json')];
        yield 'a JSON-LD format' => [self::formatted('jsonld')];
        yield 'a JSON:API format' => [self::formatted('jsonapi')];
        yield 'a problem format' => [self::formatted('problem')];
        yield 'JSON accepted first' => [Request::create('/posts', server: ['HTTP_ACCEPT' => 'application/json, text/html'])];
        yield 'a JSON suffix accepted first' => [Request::create('/posts', server: ['HTTP_ACCEPT' => 'application/ld+json'])];
    }

    #[DataProvider('apis')]
    public function testAnApiIsAnsweredWithAProblem(Request $request): void
    {
        $response = $this->handle($this->kit(), $request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame([
            'type' => 'https://example.org/problems/terms',
            'title' => 'Terms to accept',
            'status' => 403,
            'detail' => 'A version of the terms awaits your acceptance.',
            'accept' => '/accept',
            'pending' => [
                ['policy' => 'terms', 'version' => 1, 'url' => '/terms/v1'],
                ['policy' => 'privacy', 'version' => 1, 'url' => '/privacy/v1'],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    /**
     * @return iterable<string, array{Request}>
     */
    public static function pages(): iterable
    {
        yield 'an HTML format' => [self::formatted('html')];
        yield 'HTML accepted first' => [Request::create('/posts', server: ['HTTP_ACCEPT' => 'text/html, application/json'])];
        yield 'nothing accepted' => [Request::create('/posts', server: ['HTTP_ACCEPT' => ''])];
        yield 'a type ending like JSON' => [Request::create('/posts', server: ['HTTP_ACCEPT' => 'application/jsonx'])];
    }

    #[DataProvider('pages')]
    public function testAPageIsSentToTheAcceptancePage(Request $request): void
    {
        self::assertSame(303, $this->handle($this->kit(), $request)?->getStatusCode());
    }

    public function testTheProblemIsTranslated(): void
    {
        $kit = $this->kit('fr');
        $gate = new AcceptanceGate($kit->checker, new FixedMember('42'), $kit->clock, $kit->urls, translator: $kit->translator);
        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create('/api', server: ['HTTP_ACCEPT' => 'application/json']), HttpKernelInterface::MAIN_REQUEST);

        $gate->onKernelRequest($event);

        $problem = json_decode((string) $event->getResponse()?->getContent(), true);
        self::assertIsArray($problem);
        self::assertSame('about:blank', $problem['type']);
        self::assertSame('Conditions à accepter', $problem['title']);
        self::assertSame('Une version des conditions attend votre acceptation.', $problem['detail']);
    }

    private static function formatted(string $format): Request
    {
        $request = Request::create('/posts');
        $request->setRequestFormat($format);

        return $request;
    }

    private function kit(string $locale = 'en'): Kit
    {
        $kit = new Kit($locale);
        $kit->terms->publish('terms', ['en' => 'x']);
        $kit->terms->publish('privacy', ['en' => 'x']);

        return $kit;
    }

    private function handle(Kit $kit, Request $request, ?FixedMember $member = null, int $type = HttpKernelInterface::MAIN_REQUEST): ?Response
    {
        $gate = new AcceptanceGate($kit->checker, $member ?? new FixedMember('42'), $kit->clock, $kit->urls, ['logout'], ['/_profiler', '/media/'], ['/api/'], 'https://example.org/problems/terms');
        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
        $gate->onKernelRequest($event);

        return $event->getResponse();
    }
}
