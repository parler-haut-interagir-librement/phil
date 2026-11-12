<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Infrastructure\Http\TooManyRequestsResponder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigLoader;
use Twig\Loader\FilesystemLoader;

#[CoversClass(TooManyRequestsResponder::class)]
final class TooManyRequestsResponderTest extends TestCase
{
    public function testAnApiIsAnsweredAProblemInJson(): void
    {
        foreach ([$this->request(accept: 'application/json'), $this->request(accept: 'application/problem+json'), $this->request(accept: 'application/vnd.api+json, text/html'), $this->request(format: 'json'), $this->request(format: 'jsonld', accept: 'text/html'), $this->request(format: 'jsonapi'), $this->request(format: 'problem')] as $request) {
            $response = $this->respond(new TooManyRequestsResponder($this->twig()), $request);

            self::assertSame(429, $response?->getStatusCode());
            self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
            self::assertSame('{"type":"about:blank","title":"Too many requests","status":429,"detail":"Too many requests. Try again in 12 seconds.","retry_after":12}', $response->getContent());
            self::assertSame('12', $response->headers->get('Retry-After'));
            self::assertSame('"api";r=0;t=30', $response->headers->get('RateLimit'));
        }
    }

    public function testABrowserIsAnsweredThePage(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilRateLimit');

        $twig = new Environment($loader, ['strict_variables' => true]);

        foreach ([$this->request(accept: 'text/html,application/json'), $this->request(format: 'html', accept: 'application/json')] as $request) {
            $response = $this->respond(new TooManyRequestsResponder($twig), $request);

            self::assertSame(429, $response?->getStatusCode());
            self::assertStringContainsString('<html lang="fr">', (string) $response->getContent());
            self::assertStringContainsString('<title>Too many requests</title>', (string) $response->getContent());
            self::assertStringContainsString('<p role="status">Too many requests. Try again in 12 seconds.</p>', (string) $response->getContent());
            self::assertSame('12', $response->headers->get('Retry-After'));
            self::assertSame('"api";q=5', $response->headers->get('RateLimit-Policy'));
        }
    }

    public function testTheTemplateAndTheWordsAreTheApplications(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', require \dirname(__DIR__, 3).'/translations/phil_rate_limit.fr.php', 'fr', 'phil_rate_limit');

        $response = $this->respond(new TooManyRequestsResponder($this->twig(), 'mine.html.twig', $translator), $this->request());

        self::assertSame('Trop de requêtes|Trop de requêtes. Réessayez dans 12 secondes.|12|api|fr', $response?->getContent());

        $json = $this->respond(new TooManyRequestsResponder(null, translator: $translator), $this->request(accept: 'application/json'));

        self::assertSame(['type' => 'about:blank', 'title' => 'Trop de requêtes', 'status' => 429, 'detail' => 'Trop de requêtes. Réessayez dans 12 secondes.', 'retry_after' => 12], json_decode((string) $json?->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testWithoutTwigSymfonyAnswers(): void
    {
        self::assertNull($this->respond(new TooManyRequestsResponder(), $this->request()));
    }

    public function testAnotherExceptionIsLeftAlone(): void
    {
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $this->request(accept: 'application/json'), HttpKernelInterface::MAIN_REQUEST, new RuntimeException());
        new TooManyRequestsResponder($this->twig())->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function twig(): Environment
    {
        return new Environment(new TwigLoader(['mine.html.twig' => '{{ title }}|{{ detail }}|{{ retry_after }}|{{ limiter }}|{{ locale }}']), ['strict_variables' => true]);
    }

    private function request(?string $format = null, string $accept = 'text/html'): Request
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Accept', $accept);

        if (null !== $format) {
            $request->setRequestFormat($format);
        }

        return $request;
    }

    private function respond(TooManyRequestsResponder $responder, Request $request): ?Response
    {
        $decision = Decision::refused(5, 0, 30, 12)->named('api');
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new TooManyRequests($decision, ['RateLimit-Policy' => '"api";q=5', 'RateLimit' => '"api";r=0;t=30']));
        $responder->onKernelException($event);

        return $event->getResponse();
    }
}
