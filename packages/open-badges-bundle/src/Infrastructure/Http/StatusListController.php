<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use DateInterval;
use Phil\OpenBadges\Status\StatusList;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A status list served at its URL: the `BitstringStatusListCredential` of the list the application names, built from
 * its content, valid for `$maxAge` seconds and secured like the issuer's credentials. Browsers and shared caches may
 * keep it as long; a list the application does not have is a `404`.
 */
final readonly class StatusListController
{
    public function __construct(
        private StatusListIssuer $issuer,
        private Securing $securing,
        private StatusListUrls $urls,
        private ClockInterface $clock,
        private int $size,
        private int $maxAge,
        private ?StatusLists $lists = null,
    ) {
    }

    public function show(string $list): Response
    {
        $content = $this->lists?->find($list) ?? throw new NotFoundHttpException(\sprintf('The status list "%s" does not exist.', $list));
        $now = $this->clock->now();
        $credential = $this->issuer->credential($this->urls->url($list), StatusList::of($content->set, $this->size), $content->purpose, $now, $now->add(new DateInterval('PT'.$this->maxAge.'S')));
        $secured = $this->securing->secure($credential);
        $response = new Response($secured->content, Response::HTTP_OK, ['Content-Type' => $secured->mediaType]);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->setPublic();
        $response->setMaxAge($this->maxAge);

        return $response;
    }
}
