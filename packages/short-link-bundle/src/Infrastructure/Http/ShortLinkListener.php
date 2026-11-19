<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\Http;

use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\Resolution;
use Phil\ShortLinkBundle\Application\ShortLinkResolver;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Answers the requests on the short link domains before the routing: a redirect, or a `404` or a `410` thrown so that
 * the application's error page renders it. A request on another host goes on untouched.
 *
 * Only `GET` and `HEAD` are answered, each counting a visit; another method is refused before the link is read. Each
 * visit is classified by the `VisitorClassifier` from its `User-Agent` and its purpose headers — `Sec-Purpose`,
 * `Purpose`, `X-Purpose`, `X-Moz` —, and only its class goes further: neither the headers nor the address are kept. The
 * redirect is never stored by a browser or a shared cache — every visit reaches the count — and asks search engines
 * not to index it. The recorders are told of the visit on `kernel.terminate`, after the response has gone, and the
 * state of the request is dropped on `kernel.reset`.
 */
final class ShortLinkListener
{
    /** The headers a browser or a client tells the purpose of a request ahead of a click with. */
    public const array PURPOSE_HEADERS = ['Sec-Purpose', 'Purpose', 'X-Purpose', 'X-Moz'];

    private ?ShortLinkVisit $visit = null;

    /**
     * @param iterable<VisitRecorder> $recorders
     */
    public function __construct(
        private readonly ShortLinkResolver $resolver,
        private readonly iterable $recorders = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly VisitorClassifier $classifier = new KnownVisitorClassifier(),
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$this->resolver->handles($request->getHost())) {
            return;
        }

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            throw new MethodNotAllowedHttpException(['GET', 'HEAD']);
        }

        $visitor = $this->classifier->classify($request->headers->get('User-Agent'), $this->purpose($request->headers));
        $resolution = $this->resolver->resolve($request->getHost(), $request->getPathInfo(), $request->getQueryString() ?? '', $visitor);

        if (!$resolution instanceof Resolution || null === $resolution->location) {
            throw 410 === $resolution?->status ? new GoneHttpException('The short link is no longer in force.') : new NotFoundHttpException('There is no such short link.');
        }

        $response = new RedirectResponse($resolution->location, $resolution->status);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        $this->visit = $resolution->visit;
        $event->setResponse($response);
    }

    public function onKernelTerminate(): void
    {
        try {
            $visit = $this->visit;

            if ($visit instanceof ShortLinkVisit) {
                foreach ($this->recorders as $recorder) {
                    $this->tell($recorder, $visit);
                }
            }
        } finally {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->visit = null;
    }

    /**
     * The purpose headers a request sends, joined by commas; none when it sends none.
     */
    private function purpose(HeaderBag $headers): ?string
    {
        $purposes = array_filter(array_map(static fn (string $name): ?string => $headers->get($name), self::PURPOSE_HEADERS), static fn (?string $purpose): bool => null !== $purpose && '' !== $purpose);

        return [] === $purposes ? null : implode(',', $purposes);
    }

    private function tell(VisitRecorder $recorder, ShortLinkVisit $visit): void
    {
        try {
            $recorder->record($visit);
        } catch (Throwable $throwable) {
            $this->logger?->error('A short link visit could not be recorded by {recorder}: {message}', ['recorder' => $recorder::class, 'message' => $throwable->getMessage(), 'exception' => $throwable]);
        }
    }
}
